use async_channel::{Receiver, Sender, bounded};
use sqlx::{Acquire, PgPool, Row, postgres::PgPoolOptions};
use std::{collections::HashSet, env, io, os::unix::fs::PermissionsExt, path::Path, sync::Arc};
use tokio::{
    fs as tokio_fs,
    io::{AsyncBufReadExt, BufReader},
    process::Command,
};

// ERROR

#[derive(thiserror::Error, Debug)]
pub enum Error {
    #[error("IO error")]
    Io(#[from] io::Error),
    #[error("Join error")]
    Join(#[from] tokio::task::JoinError),
    #[error("Environment variable not set: {0}")]
    MissingEnv(String),
    #[error("Database error: {0}")]
    Database(#[from] sqlx::Error),
    #[error("Package not found: {0}")]
    PackageNotFound(String),
    #[error("Command failed: {0}")]
    Command(String),
}

type Result<T> = std::result::Result<T, Error>;

// CONSTS

const ARCH_DST: &str = "/app/mirrors/arch";
// const ARCH_URL: &str = "rsync://mirror.pseudoform.org/packages/";
const ARCH_URL: &str = "rsync://frankfurt.mirror.pkgbuild.com/packages/";

const ARTIX_DST: &str = "/app/mirrors/artix";
const ARTIX_URL: &str = "rsync://ftp.sh.cvut.cz/artix-linux/";

const RSYNC_ARGS: &[&str] = &[
    "-v",
    "-r",
    "-u",
    "-l",
    "-h",
    "-p",
    "-t",
    "-h",
    "-P",
    "--delete-delay",
    "--delay-updates",
    "--no-motd",
];

const ARCH_REPOS: &[&str] = &["core", "extra", "multilib", "pool", "lastsync"];
const ARTIX_REPOS: &[&str] = &["galaxy", "lib32", "system", "world", "lastsync"];

const TMPDIR: &str = "/tmp/package-update";
const FILES_TMP: &str = "/tmp/package-updater/package-files";
const MAN_DST: &str = "/app/man";

const DB_FILES: &[(&str, &str)] = &[
    ("/app/mirrors/neoarch/matrix/os/x86_64/matrix.db", "matrix"),
    ("/app/mirrors/artix/system/os/x86_64/system.db", "system"),
    ("/app/mirrors/artix/world/os/x86_64/world.db", "world"),
    ("/app/mirrors/artix/galaxy/os/x86_64/galaxy.db", "galaxy"),
    ("/app/mirrors/artix/lib32/os/x86_64/lib32.db", "lib32"),
    ("/app/mirrors/arch/core/os/x86_64/core.db", "core"),
    ("/app/mirrors/arch/extra/os/x86_64/extra.db", "extra"),
    (
        "/app/mirrors/arch/multilib/os/x86_64/multilib.db",
        "multilib",
    ),
];

const REPO_PATHS: &[(&str, &str)] = &[
    ("matrix", "/app/mirrors/neoarch/matrix/os/x86_64"),
    ("system", "/app/mirrors/artix/system/os/x86_64"),
    ("world", "/app/mirrors/artix/world/os/x86_64"),
    ("galaxy", "/app/mirrors/artix/galaxy/os/x86_64"),
    ("lib32", "/app/mirrors/artix/lib32/os/x86_64"),
    ("core", "/app/mirrors/arch/core/os/x86_64"),
    ("extra", "/app/mirrors/arch/extra/os/x86_64"),
    ("multilib", "/app/mirrors/arch/multilib/os/x86_64"),
];

// MAIN

#[tokio::main]
async fn main() -> Result<()> {
    tracing_subscriber::fmt()
        .with_env_filter(
            tracing_subscriber::EnvFilter::try_from_default_env()
                .unwrap_or_else(|_| tracing_subscriber::EnvFilter::new("info")),
        )
        .with_target(false)
        .with_thread_ids(false)
        .with_file(false)
        .with_line_number(false)
        .init();

    let db_url = format!(
        "postgres://{}:{}@{}:{}/{}",
        env::var("DB_USER").map_err(|_| Error::MissingEnv("DB_USER".into()))?,
        env::var("DB_PASS").map_err(|_| Error::MissingEnv("DB_PASS".into()))?,
        env::var("DB_HOST").map_err(|_| Error::MissingEnv("DB_HOST".into()))?,
        env::var("DB_PORT").map_err(|_| Error::MissingEnv("DB_PORT".into()))?,
        env::var("DB_NAME").map_err(|_| Error::MissingEnv("DB_NAME".into()))?,
    );

    let pool = PgPoolOptions::new()
        .max_connections(16)
        .connect(&db_url)
        .await?;

    loop {
        tracing::info!("Starting package update");

        tracing::debug!("Starting phase sync_repos");
        sync_repos().await?;

        tracing::info!("Building package cache from DB...");
        let cache = build_cache(&pool).await?;
        tracing::info!("Cache contains {} packages", cache.len());

        tracing::debug!("Starting phase update_packages");
        update_packages(&pool, &cache).await?;

        tracing::debug!("Starting phase update_files");
        update_files(&pool, &cache).await?;

        tracing::info!("Package update completed successfully");

        tokio::time::sleep(tokio::time::Duration::from_secs(24 * 3600)).await;
    }
}

// REPO SYNC

async fn sync_repos() -> io::Result<()> {
    for repo in ARCH_REPOS {
        sync_repo(ARCH_URL, ARCH_DST, repo).await?;
    }
    for repo in ARTIX_REPOS {
        sync_repo(ARTIX_URL, ARTIX_DST, repo).await?;
    }
    Ok(())
}

async fn stream_output<R>(reader: R, prefix: String) -> io::Result<()>
where
    R: tokio::io::AsyncRead + Unpin,
{
    let mut lines = BufReader::new(reader).lines();

    while let Some(line) = lines.next_line().await? {
        // Strip carriage returns (from progress bars like rsync)
        let cleaned = line.replace('\r', "");
        if !cleaned.is_empty() {
            tracing::info!("{} {}", prefix, cleaned);
        }
    }

    Ok(())
}

async fn sync_repo(base_url: &str, dst: &str, repo: &str) -> io::Result<()> {
    let src = format!("{}{}", base_url, repo);

    tracing::info!("[rsync] Starting sync: {} -> {}", src, dst);

    let mut child = Command::new("rsync")
        .args(RSYNC_ARGS)
        .arg(&src)
        .arg(dst)
        .stdout(std::process::Stdio::piped())
        .stderr(std::process::Stdio::piped())
        .spawn()?;

    let stdout = child.stdout.take().expect("rsync stdout not captured");
    let stderr = child.stderr.take().expect("rsync stderr not captured");

    let stdout_handle = tokio::spawn(stream_output(stdout, format!("[rsync {} stdout]", repo)));
    let stderr_handle = tokio::spawn(stream_output(stderr, format!("[rsync {} stderr]", repo)));

    let status = child.wait().await?;

    let _ = stdout_handle.await;
    let _ = stderr_handle.await;

    if status.success() {
        tracing::info!("[rsync] Completed sync for {}", repo);
        Ok(())
    } else {
        Err(io::Error::other(format!(
            "rsync failed for {} with exit code: {:?}",
            repo,
            status.code()
        )))
    }
}

// PACKAGE CACHE

async fn build_cache(pool: &PgPool) -> Result<HashSet<String>> {
    let mut cache: HashSet<String> = HashSet::with_capacity(100000);

    let rows = sqlx::query("SELECT repo || '/' || name || '-' || version AS key FROM package_meta")
        .fetch_all(pool)
        .await?;

    for row in rows {
        let key: String = row.get(0);
        cache.insert(key);
    }

    Ok(cache)
}

// PACKAGE UPDATE

async fn update_packages(pool: &PgPool, cache: &HashSet<String>) -> Result<()> {
    tracing::info!("==== Starting package database update ====");

    let _ = tokio_fs::remove_dir_all(TMPDIR).await;
    tokio_fs::create_dir_all(TMPDIR).await?;

    let copy_futures: Vec<_> = DB_FILES
        .iter()
        .map(|(src, _)| {
            let dst = Path::new(TMPDIR).join(Path::new(src).file_name().unwrap());
            let src = src.to_string();
            tokio::spawn(async move { tokio_fs::copy(&src, dst).await })
        })
        .collect();

    for f in copy_futures {
        f.await??;
    }

    for (dbfile_path, repo_name) in DB_FILES {
        let dbfile_name = Path::new(dbfile_path)
            .file_name()
            .unwrap()
            .to_str()
            .unwrap();
        let dbfile = Path::new(TMPDIR).join(dbfile_name);
        let extract_dir = Path::new(TMPDIR).join(repo_name);

        tokio_fs::create_dir_all(&extract_dir).await?;

        // Extract using tar executable
        tracing::info!("[tar] Extracting {} to {}", dbfile_name, repo_name);

        let mut child = Command::new("tar")
            .args([
                "-xf",
                dbfile.to_str().unwrap(),
                "-C",
                extract_dir.to_str().unwrap(),
            ])
            .stdout(std::process::Stdio::piped())
            .stderr(std::process::Stdio::piped())
            .spawn()?;

        let stdout = child.stdout.take().expect("tar stdout not captured");
        let stderr = child.stderr.take().expect("tar stderr not captured");

        let stdout_handle = tokio::spawn(stream_output(
            stdout,
            format!("[tar {} stdout]", dbfile_name),
        ));
        let stderr_handle = tokio::spawn(stream_output(
            stderr,
            format!("[tar {} stderr]", dbfile_name),
        ));

        let status = child.wait().await?;

        let _ = stdout_handle.await;
        let _ = stderr_handle.await;

        if !status.success() {
            return Err(Error::Command(format!(
                "tar failed for {} with exit code: {:?}",
                dbfile_name,
                status.code()
            )));
        }

        let mut entries = tokio_fs::read_dir(&extract_dir).await?;
        while let Some(entry) = entries.next_entry().await? {
            let desc_path = entry.path().join("desc");
            if !desc_path.exists() {
                continue;
            }

            let desc = tokio_fs::read_to_string(&desc_path).await?;
            let pkg_name = extract_field(&desc, "NAME")
                .ok_or_else(|| Error::Command(format!("Missing NAME in {:?}", desc_path)))?;
            let pkg_ver = extract_field(&desc, "VERSION")
                .ok_or_else(|| Error::Command(format!("Missing VERSION in {:?}", desc_path)))?;

            let cache_key = format!("{}/{}-{}", repo_name, pkg_name, pkg_ver);
            if cache.contains(&cache_key) {
                continue;
            }

            let pkg_desc = extract_field(&desc, "DESC").unwrap_or_default();
            let pkg_url = extract_field(&desc, "URL").unwrap_or_default();

            sqlx::query(
                r#"
                INSERT INTO package_meta (name, repo, version, description, url, last_updated)
                VALUES ($1, $2, $3, $4, $5, CURRENT_DATE)
                ON CONFLICT (name, repo) DO UPDATE
                SET version = EXCLUDED.version,
                    description = EXCLUDED.description,
                    url = EXCLUDED.url,
                    last_updated = EXCLUDED.last_updated
                "#,
            )
            .bind(&pkg_name)
            .bind(repo_name)
            .bind(&pkg_ver)
            .bind(&pkg_desc)
            .bind(&pkg_url)
            .execute(pool)
            .await?;
        }
    }

    for (_dbfile_path, repo_name) in DB_FILES {
        let extract_dir = Path::new(TMPDIR).join(repo_name);
        let mut entries = tokio_fs::read_dir(&extract_dir).await?;

        while let Some(entry) = entries.next_entry().await? {
            let desc_path = entry.path().join("desc");
            if !desc_path.exists() {
                continue;
            }

            let desc = tokio_fs::read_to_string(&desc_path).await?;
            let pkg_name = extract_field(&desc, "NAME").unwrap_or_default();
            let pkg_ver = extract_field(&desc, "VERSION").unwrap_or_default();

            let cache_key = format!("{}/{}-{}", repo_name, pkg_name, pkg_ver);
            if cache.contains(&cache_key) {
                continue;
            }

            let pkg_id: i32 =
                match sqlx::query("SELECT id FROM package_meta WHERE name = $1 AND repo = $2")
                    .bind(&pkg_name)
                    .bind(repo_name)
                    .fetch_optional(pool)
                    .await?
                {
                    Some(row) => row.get(0),
                    None => {
                        return Err(Error::PackageNotFound(format!(
                            "{}/{}",
                            repo_name, pkg_name
                        )));
                    }
                };

            sqlx::query("DELETE FROM package_relations WHERE package_id = $1")
                .bind(pkg_id)
                .execute(pool)
                .await?;

            sqlx::query(
                r#"
                INSERT INTO components (name, is_virtual)
                VALUES ($1, NOT EXISTS (SELECT 1 FROM package_meta WHERE name = $1))
                ON CONFLICT (name) DO UPDATE
                SET is_virtual = NOT EXISTS (SELECT 1 FROM package_meta WHERE name = EXCLUDED.name)
                "#,
            )
            .bind(&pkg_name)
            .execute(pool)
            .await?;

            sqlx::query(
                r#"
                INSERT INTO package_relations (package_id, component_id, relation_type, version_expr, relation_description)
                SELECT $1, id, 'PROVIDES'::relation_type, NULL, NULL FROM components WHERE name = $2
                ON CONFLICT (package_id, component_id, relation_type, version_expr) DO NOTHING
                "#
            )
            .bind(pkg_id)
            .bind(&pkg_name)
            .execute(pool)
            .await?;

            for block in [
                "DEPENDS",
                "OPTDEPENDS",
                "MAKEDEPENDS",
                "CHECKDEPENDS",
                "PROVIDES",
                "CONFLICTS",
                "REPLACES",
            ] {
                let block_content = extract_block(&desc, block);

                for line in block_content.lines() {
                    if line.is_empty() {
                        continue;
                    }

                    let (name, version, description) = parse_relation_line(line, block);

                    sqlx::query(
                        r#"
                        INSERT INTO components (name, is_virtual)
                        VALUES ($1, NOT EXISTS (SELECT 1 FROM package_meta WHERE name = $1))
                        ON CONFLICT (name) DO UPDATE
                        SET is_virtual = NOT EXISTS (SELECT 1 FROM package_meta WHERE name = EXCLUDED.name)
                        "#
                    )
                    .bind(&name)
                    .execute(pool)
                    .await?;

                    sqlx::query(
                        r#"
                        INSERT INTO package_relations (package_id, component_id, relation_type, version_expr, relation_description)
                        SELECT $1, id, $2::relation_type, NULLIF($3, ''), NULLIF($4, '') FROM components WHERE name = $5
                        ON CONFLICT (package_id, component_id, relation_type, version_expr) DO NOTHING
                        "#
                    )
                    .bind(pkg_id)
                    .bind(block)
                    .bind(&version)
                    .bind(&description)
                    .bind(&name)
                    .execute(pool)
                    .await?;
                }
            }
        }
    }

    tokio_fs::remove_dir_all(TMPDIR).await?;
    tracing::info!("==== Package database update complete ====");

    Ok(())
}

// FILE UPDATE - PARALLELIZED WITH 16 WORKERS

#[derive(Clone)]
struct PackageTask {
    path: std::path::PathBuf,
    repo: String,
}

async fn update_files(pool: &PgPool, cache: &HashSet<String>) -> Result<()> {
    tracing::info!("==== Starting package files update ====");

    let _ = tokio_fs::remove_dir_all(FILES_TMP).await;
    tokio_fs::create_dir_all(FILES_TMP).await?;
    tokio_fs::create_dir_all(MAN_DST).await?;

    // Use async-channel for multi-consumer (cloneable receiver)
    let (tx, rx): (Sender<PackageTask>, Receiver<PackageTask>) = bounded(100000);
    let rx = Arc::new(rx);

    // Scan all repos and populate queue
    for (repo, path) in REPO_PATHS {
        let mut entries = tokio_fs::read_dir(path).await?;
        while let Some(entry) = entries.next_entry().await? {
            let path = entry.path();
            let name = path.file_name().unwrap().to_str().unwrap();

            if name.ends_with(".sig") || name.ends_with(".old") {
                continue;
            }

            if !name.contains("-x86_64.pkg.tar.") {
                continue;
            }

            let cache_key = format!("{}/{}", repo, &name[0..name.find("-x86_64.pkg.").unwrap()]);

            if cache.contains(&cache_key) {
                continue;
            }

            tx.send(PackageTask {
                path,
                repo: repo.to_string(),
            })
            .await
            .unwrap();
        }
    }

    drop(tx); // Close sender so workers know when queue is empty

    tracing::info!("Creating package queue");
    let mut handles = Vec::new();
    for worker_id in 0..16 {
        let rx = Arc::clone(&rx);
        let pool = pool.clone();
        let handle = tokio::spawn(async move {
            worker_loop(worker_id, rx, pool).await;
        });
        handles.push(handle);
    }
    tracing::info!("Package queue created");

    // Wait for all workers to complete
    for handle in handles {
        handle.await?;
    }

    tracing::info!("==== Package files update complete ====");
    Ok(())
}

async fn worker_loop(worker_id: usize, rx: Arc<Receiver<PackageTask>>, pool: PgPool) {
    tracing::debug!("[Worker {}] Started", worker_id);

    loop {
        match rx.recv().await {
            Ok(pkg_task) => {
                if let Err(e) =
                    handle_package_file_with_transaction(&pool, &pkg_task.path, &pkg_task.repo)
                        .await
                {
                    tracing::error!(
                        "[Worker {}] Error processing {:?}: {}",
                        worker_id,
                        pkg_task.path,
                        e
                    );
                }
            }
            Err(_) => {
                tracing::debug!("[Worker {}] Channel closed, shutting down", worker_id);
                break;
            }
        }
    }
}

async fn handle_package_file_with_transaction(
    pool: &PgPool,
    pkgfile: &Path,
    repo: &str,
) -> Result<()> {
    // Acquire a connection and start transaction
    let mut conn = pool.acquire().await?;
    let mut tx = conn.begin().await?;

    let result = handle_package_file_tx(&mut tx, pkgfile, repo).await;

    match result {
        Ok(_) => {
            tx.commit().await?;
            Ok(())
        }
        Err(e) => {
            tx.rollback().await?;
            Err(e)
        }
    }
}

// Transaction-based version of handle_package_file
async fn handle_package_file_tx<'a>(
    tx: &mut sqlx::Transaction<'a, sqlx::Postgres>,
    pkgfile: &Path,
    repo: &str,
) -> Result<()> {
    let filename = pkgfile.file_name().unwrap().to_str().unwrap();

    // Find package name from DB by matching filename pattern
    let pkg_name: String = match sqlx::query(
        "SELECT name FROM package_meta WHERE $1 LIKE name || '-' || version || '-%' AND repo = $2",
    )
    .bind(filename)
    .bind(repo)
    .fetch_optional(&mut **tx)
    .await?
    {
        Some(row) => {
            let name: String = row.get(0);
            tracing::info!("Classyfing {}/{}", repo, name);
            name
        }
        None => {
            tracing::warn!("Package not found for {}/{}, skipping", repo, filename);
            return Ok(());
        }
    };

    let extract_dir = Path::new(FILES_TMP).join(repo).join(&pkg_name);
    tokio_fs::create_dir_all(&extract_dir).await?;

    // Extract package
    let output = Command::new("tar")
        .args([
            "-xf",
            pkgfile.to_str().unwrap(),
            "-C",
            extract_dir.to_str().unwrap(),
        ])
        .output()
        .await?;

    if !output.status.success() {
        return Err(Error::Command(format!(
            "tar failed: {}",
            String::from_utf8_lossy(&output.stderr)
        )));
    }

    // Find all files (not starting with .)
    let find_output = Command::new("find")
        .arg(&extract_dir)
        .args(["(", "-type", "f", "-o", "-type", "l", ")"])
        .arg("-printf")
        .arg("%P\\n")
        .output()
        .await?;

    let files = String::from_utf8_lossy(&find_output.stdout);

    for file in files.lines() {
        if file.starts_with('.') {
            continue;
        }

        let file_path = extract_dir.join(file);

        if !file_path.exists() {
            continue;
        }

        let file_type = classify_file(&file_path, file).await?;

        let metadata = tokio_fs::metadata(&file_path).await?;
        let mode = metadata.permissions().mode() & 0o777;
        let mode = format!("{:o}", mode).parse::<i32>().unwrap();
        let size = metadata.len();

        // Insert file and get ID
        let file_id: i32 = sqlx::query(
            r#"
            INSERT INTO package_files (package_id, file_path, file_type, file_mode, file_size)
            VALUES (
                (SELECT id FROM package_meta WHERE repo = $1 AND name = $2),
                '/' || $3,
                $4::package_file_type,
                $5,
                $6
            )
            ON CONFLICT (package_id, file_path) DO UPDATE SET
                file_type = EXCLUDED.file_type,
                file_mode = EXCLUDED.file_mode,
                file_size = EXCLUDED.file_size
            RETURNING id
            "#,
        )
        .bind(repo)
        .bind(&pkg_name)
        .bind(file)
        .bind(&file_type)
        .bind(mode)
        .bind(size as i64)
        .fetch_one(&mut **tx)
        .await?
        .get(0);

        // Classify specific types - pass transaction reference
        match file_type.as_str() {
            "ELFBIN" => classify_elfbin_tx(tx, &extract_dir, file, file_id).await?,
            "CONF" => classify_conf_tx(tx, &extract_dir, &pkg_name, file, file_id).await?,
            "SCRIPT" => classify_script_tx(tx, &extract_dir, file, file_id).await?,
            "PACMAN_HOOK" => classify_pacman_hook_tx(tx, &extract_dir, file, file_id).await?,
            "SYMLINK" => classify_symlink_tx(tx, &extract_dir, file, file_id).await?,
            _ => {}
        }

        // Copy man pages
        if file.starts_with("usr/share/man/man1/") || file.starts_with("usr/share/man/man8/") {
            let section = Path::new(file).parent().unwrap().file_name().unwrap();
            let man_dir = Path::new(MAN_DST).join(repo).join(&pkg_name).join(section);
            tokio_fs::create_dir_all(&man_dir).await?;

            let target = man_dir.join(Path::new(file).file_name().unwrap());
            tokio_fs::copy(&file_path, target).await?;
        }
    }

    // Cleanup
    tokio_fs::remove_dir_all(&extract_dir).await?;
    Ok(())
}

async fn classify_file(path: &Path, file: &str) -> io::Result<String> {
    // Check if symlink
    let metadata = tokio_fs::symlink_metadata(path).await?;
    if metadata.file_type().is_symlink() {
        return Ok("SYMLINK".into());
    }

    let output = Command::new("file").arg("-b").arg(path).output().await?;

    let magic = String::from_utf8_lossy(&output.stdout).to_lowercase();

    let mut ftype = match magic.as_str() {
        m if m.contains("empty") => "EMPTY",
        m if m.contains("elf") && m.contains("executable") => "ELFBIN",
        m if m.contains("elf") && m.contains("shared") => "ELFLIB",
        m if m.contains("script") && m.contains("text executable") => "SCRIPT",
        m if m.contains("text") => "TEXT",
        m if m.contains("data") => "DATA",
        _ => "OTHER",
    }
    .to_string();

    // Path-based overrides
    if file.ends_with(".conf") || file.contains("/etc/") && file.ends_with(".conf") {
        ftype = "CONF".into();
    } else if file.starts_with("usr/share/libalpm/hooks/") && file.ends_with(".hook") {
        ftype = "PACMAN_HOOK".into();
    }

    Ok(ftype)
}

async fn classify_elfbin_tx<'a>(
    tx: &mut sqlx::Transaction<'a, sqlx::Postgres>,
    pkgroot: &Path,
    file: &str,
    file_id: i32,
) -> Result<()> {
    let path = pkgroot.join(file);

    // Get symbols from objdump
    let syms_output = Command::new("objdump")
        .args(["-T", path.to_str().unwrap()])
        .output()
        .await?;
    let syms = String::from_utf8_lossy(&syms_output.stdout);

    let all_syms_output = Command::new("objdump")
        .args(["-t", path.to_str().unwrap()])
        .output()
        .await?;
    let all_syms = String::from_utf8_lossy(&all_syms_output.stdout);

    let strings_output = Command::new("strings")
        .arg("-a")
        .arg(path.to_str().unwrap())
        .output()
        .await?;
    let strings = String::from_utf8_lossy(&strings_output.stdout);

    let combined = format!("{} {} {}", syms, all_syms, strings);

    let has = |pat: &str| combined.contains(pat);

    let is_static = Command::new("file")
        .arg(&path)
        .output()
        .await?
        .stdout
        .windows(6)
        .any(|w| w == b"static");
    let is_dynamic = Command::new("file")
        .arg(&path)
        .output()
        .await?
        .stdout
        .windows(7)
        .any(|w| w == b"dynamic");

    sqlx::query(
        r#"
        INSERT INTO package_file_elfbin VALUES (
            $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19,
            $20, $21, $22, $23, $24, $25, $26, $27, $28, $29, $30, $31, $32, $33, $34, $35, $36,
            $37, $38, $39, $40, $41, $42, $43, $44, $45, $46, $47, $48, $49, $50, $51, $52
        )
        ON CONFLICT (file_id) DO UPDATE SET
            is_static = EXCLUDED.is_static, is_dynamic = EXCLUDED.is_dynamic,
            binary_cxx = EXCLUDED.binary_cxx, binary_go = EXCLUDED.binary_go, binary_rust = EXCLUDED.binary_rust,
            compression_bzip2 = EXCLUDED.compression_bzip2, compression_lzma = EXCLUDED.compression_lzma, compression_zlib = EXCLUDED.compression_zlib,
            directory_create = EXCLUDED.directory_create, directory_read = EXCLUDED.directory_read, directory_remove = EXCLUDED.directory_remove,
            embeds_lua = EXCLUDED.embeds_lua, embeds_python = EXCLUDED.embeds_python,
            execution_deamonizes = EXCLUDED.execution_deamonizes, execution_debugs = EXCLUDED.execution_debugs, execution_does = EXCLUDED.execution_does,
            file_create = EXCLUDED.file_create, file_create_temporary = EXCLUDED.file_create_temporary, file_delete = EXCLUDED.file_delete,
            file_read = EXCLUDED.file_read, file_rename = EXCLUDED.file_rename, file_write = EXCLUDED.file_write,
            kernel_device_interaction = EXCLUDED.kernel_device_interaction, kernel_event_io = EXCLUDED.kernel_event_io, kernel_syscall = EXCLUDED.kernel_syscall,
            memory_map = EXCLUDED.memory_map, memory_shm = EXCLUDED.memory_shm,
            metadata_modify = EXCLUDED.metadata_modify, metadata_query = EXCLUDED.metadata_query,
            networking_dns = EXCLUDED.networking_dns, networking_has = EXCLUDED.networking_has, networking_http = EXCLUDED.networking_http,
            networking_server = EXCLUDED.networking_server, networking_tls = EXCLUDED.networking_tls, networking_udp = EXCLUDED.networking_udp,
            privilege_changes = EXCLUDED.privilege_changes,
            supports_audio = EXCLUDED.supports_audio, supports_cryptography = EXCLUDED.supports_cryptography, supports_encoding_conversion = EXCLUDED.supports_encoding_conversion,
            supports_images = EXCLUDED.supports_images, supports_localization = EXCLUDED.supports_localization, supports_unicode = EXCLUDED.supports_unicode,
            suspicious_loader_manipulation = EXCLUDED.suspicious_loader_manipulation, suspicious_sandboxing = EXCLUDED.suspicious_sandboxing, suspicious_self_memory_access = EXCLUDED.suspicious_self_memory_access,
            system_env_vars = EXCLUDED.system_env_vars, system_info_detect = EXCLUDED.system_info_detect, system_performance = EXCLUDED.system_performance, system_user_awareness = EXCLUDED.system_user_awareness,
            thread_sync = EXCLUDED.thread_sync, thread_use = EXCLUDED.thread_use
        "#
    )
    .bind(file_id)
    .bind(is_static)
    .bind(is_dynamic)
    .bind(has("__cxa_") || has("std::"))
    .bind(has("go.buildid") || has("runtime.go"))
    .bind(has("rust_eh_") || has("core::"))
    .bind(has("bz2"))
    .bind(has("lzma") || has("xz"))
    .bind(has("zlib") || has("deflate"))
    .bind(has("mkdir"))
    .bind(has("readdir") || has("getdents"))
    .bind(has("rmdir"))
    .bind(has("lua_"))
    .bind(has("Py_Initialize"))
    .bind(has("daemon") || has("setsid"))
    .bind(has("ptrace"))
    .bind(has("execve") || has("execvp"))
    .bind(has("creat") || has("openat"))
    .bind(has("mkstemp") || has("tmpfile"))
    .bind(has("unlink") || has("remove"))
    .bind(has("read") || has("pread"))
    .bind(has("rename"))
    .bind(has("write") || has("pwrite"))
    .bind(has("ioctl"))
    .bind(has("epoll") || has("poll") || has("select"))
    .bind(has("syscall"))
    .bind(has("mmap"))
    .bind(has("shm_"))
    .bind(has("chmod") || has("chown"))
    .bind(has("stat") || has("lstat") || has("fstat"))
    .bind(has("getaddrinfo") || has("inet_"))
    .bind(has("socket") || has("connect"))
    .bind(has("curl_") || has("libcurl"))
    .bind(has("bind") || has("listen") || has("accept"))
    .bind(has("SSL_") || has("TLS_"))
    .bind(has("sendto") || has("recvfrom"))
    .bind(has("setuid") || has("capset"))
    .bind(has("ogg") || has("mp3") || has("flac"))
    .bind(has("AES_") || has("SHA") || has("RSA_") || has("EVP_"))
    .bind(has("iconv"))
    .bind(has("png_") || has("jpeg_") || has("tiff_"))
    .bind(has("setlocale") || has("gettext"))
    .bind(has("mbstowcs") || has("wchar"))
    .bind(has("LD_PRELOAD"))
    .bind(has("seccomp"))
    .bind(has("/proc/self/mem"))
    .bind(has("getenv") || has("putenv"))
    .bind(has("uname") || has("sysinfo"))
    .bind(has("getrusage") || has("clock_gettime"))
    .bind(has("getuid") || has("getpwuid"))
    .bind(has("futex"))
    .bind(has("pthread_"))
    .execute(&mut **tx)
    .await?;

    Ok(())
}

async fn classify_conf_tx<'a>(
    tx: &mut sqlx::Transaction<'a, sqlx::Postgres>,
    pkgroot: &Path,
    _pkg_name: &str,
    file: &str,
    file_id: i32,
) -> Result<()> {
    let confbase = Path::new(file).file_name().unwrap().to_str().unwrap();
    let cache_dir = pkgroot.join(".strings-cache");
    tokio_fs::create_dir_all(&cache_dir).await?;

    // Find binaries
    let mut bin_paths = vec![];
    for bin_dir in [pkgroot.join("usr/bin"), pkgroot.join("usr/local/bin")] {
        if let Ok(mut entries) = tokio_fs::read_dir(&bin_dir).await {
            while let Ok(Some(entry)) = entries.next_entry().await {
                let path = entry.path();
                if path.is_file() {
                    bin_paths.push(path);
                }
            }
        }
    }

    for bin in bin_paths {
        let cachefile = cache_dir
            .join(bin.strip_prefix(pkgroot).unwrap())
            .with_extension("strings");
        tokio_fs::create_dir_all(cachefile.parent().unwrap()).await?;

        if !cachefile.exists() {
            let strings_out = Command::new("strings").arg("-a").arg(&bin).output().await?;
            tokio_fs::write(&cachefile, &strings_out.stdout).await?;
        }

        let cache_content = tokio_fs::read_to_string(&cachefile).await?;
        if cache_content.contains(confbase) {
            sqlx::query(
                "INSERT INTO package_file_conf_users (file_id, conf_user) VALUES ($1, '/' || $2) ON CONFLICT (file_id) DO UPDATE SET conf_user = EXCLUDED.conf_user"
            )
            .bind(file_id)
            .bind(bin.strip_prefix(pkgroot).unwrap().to_str().unwrap())
            .execute(&mut **tx)
            .await?;
            break;
        }
    }

    Ok(())
}

async fn classify_script_tx<'a>(
    tx: &mut sqlx::Transaction<'a, sqlx::Postgres>,
    pkgroot: &Path,
    file: &str,
    file_id: i32,
) -> Result<()> {
    let path = pkgroot.join(file);
    let bytes = tokio_fs::read(&path).await?;
    let content = String::from_utf8_lossy(&bytes);
    let firstline = content.lines().next().unwrap_or("");

    let shebang = firstline.trim_start_matches("#!").trim();
    let parts: Vec<&str> = shebang.split_whitespace().collect();

    let interp = if parts.len() >= 2
        && (parts[0] == "/usr/bin/env" || parts[0] == "/bin/env" || parts[0] == "env")
    {
        let mut idx = 1;
        if parts.get(1) == Some(&"-S") {
            idx = 2;
        }
        parts
            .get(idx)
            .map(|s| {
                Path::new(s)
                    .file_name()
                    .map(|n| n.to_str().unwrap_or(""))
                    .unwrap_or(*s)
            })
            .unwrap_or("")
            .to_string()
    } else if !parts.is_empty() {
        Path::new(parts[0])
            .file_name()
            .map(|n| n.to_str().unwrap_or(""))
            .unwrap_or(parts[0])
            .to_string()
    } else {
        "".to_string()
    };

    sqlx::query(
        "INSERT INTO package_file_script (file_id, script_executable) VALUES ($1, NULLIF($2, '')) ON CONFLICT (file_id) DO UPDATE SET script_executable = EXCLUDED.script_executable"
    )
    .bind(file_id)
    .bind(&interp)
    .execute(&mut **tx)
    .await?;

    Ok(())
}

async fn classify_pacman_hook_tx<'a>(
    tx: &mut sqlx::Transaction<'a, sqlx::Postgres>,
    pkgroot: &Path,
    file: &str,
    file_id: i32,
) -> Result<()> {
    let path = pkgroot.join(file);
    let content = tokio_fs::read_to_string(&path).await?;

    let mut current_section = "";
    let mut trigger_type = String::new();
    let mut on_install = false;
    let mut on_upgrade = false;
    let mut on_remove = false;
    let mut trigger_targets: Vec<String> = vec![];
    let mut action_when = "PostTransaction".to_string();
    let mut action_desc = String::new();

    for line in content.lines() {
        let line = line.trim();

        if line.starts_with('[') && line.ends_with(']') {
            if current_section == "Trigger" && !trigger_type.is_empty() {
                let trigger_id: i32 = sqlx::query(
                    r#"
                    INSERT INTO package_file_pacman_hook_triggers
                    (file_id, trigger_type, trigger_on_install, trigger_on_upgrade, trigger_on_remove)
                    VALUES ($1, $2::pacman_hook_trigger_type, $3, $4, $5)
                    ON CONFLICT (file_id, trigger_type, trigger_on_install, trigger_on_upgrade, trigger_on_remove) DO UPDATE SET
                        trigger_type = EXCLUDED.trigger_type,
                        trigger_on_install = EXCLUDED.trigger_on_install,
                        trigger_on_upgrade = EXCLUDED.trigger_on_upgrade,
                        trigger_on_remove = EXCLUDED.trigger_on_remove
                    RETURNING id
                    "#
                )
                .bind(file_id)
                .bind(&trigger_type)
                .bind(on_install)
                .bind(on_upgrade)
                .bind(on_remove)
                .fetch_one(&mut **tx)
                .await?
                .get(0);

                for tgt in &trigger_targets {
                    sqlx::query(
                        "INSERT INTO pacman_hook_trigger_targets (trigger_id, trigger_target) VALUES ($1, $2) ON CONFLICT DO NOTHING"
                    )
                    .bind(trigger_id)
                    .bind(tgt)
                    .execute(&mut **tx)
                    .await?;
                }

                trigger_type.clear();
                on_install = false;
                on_upgrade = false;
                on_remove = false;
                trigger_targets.clear();
            }

            current_section = &line[1..line.len() - 1];
        } else if let Some(eq_pos) = line.find('=') {
            let key = line[..eq_pos].trim();
            let val = line[eq_pos + 1..].trim();

            match (current_section, key) {
                ("Trigger", "Type") => trigger_type = val.to_string(),
                ("Trigger", "Operation") => match val {
                    "Install" => on_install = true,
                    "Upgrade" => on_upgrade = true,
                    "Remove" => on_remove = true,
                    _ => {}
                },
                ("Trigger", "Path") | ("Trigger", "Target") => {
                    trigger_targets.push(val.to_string())
                }
                ("Action", "When") => action_when = val.to_string(),
                ("Action", "Description") => action_desc = val.to_string(),
                _ => {}
            }
        }
    }

    if current_section == "Trigger" && !trigger_type.is_empty() {
        let trigger_id: i32 = sqlx::query(
            r#"
            INSERT INTO package_file_pacman_hook_triggers
            (file_id, trigger_type, trigger_on_install, trigger_on_upgrade, trigger_on_remove)
            VALUES ($1, $2::pacman_hook_trigger_type, $3, $4, $5)
            ON CONFLICT (file_id, trigger_type, trigger_on_install, trigger_on_upgrade, trigger_on_remove) DO UPDATE SET
                trigger_type = EXCLUDED.trigger_type,
                trigger_on_install = EXCLUDED.trigger_on_install,
                trigger_on_upgrade = EXCLUDED.trigger_on_upgrade,
                trigger_on_remove = EXCLUDED.trigger_on_remove
            RETURNING id
            "#
        )
        .bind(file_id)
        .bind(&trigger_type)
        .bind(on_install)
        .bind(on_upgrade)
        .bind(on_remove)
        .fetch_one(&mut **tx)
        .await?
        .get(0);

        for tgt in &trigger_targets {
            sqlx::query(
                "INSERT INTO pacman_hook_trigger_targets (trigger_id, trigger_target) VALUES ($1, $2) ON CONFLICT DO NOTHING"
            )
            .bind(trigger_id)
            .bind(tgt)
            .execute(&mut **tx)
            .await?;
        }
    }

    if action_when.is_empty() {
        action_when = "PostTransaction".into();
    }

    sqlx::query(
        r#"
        INSERT INTO package_file_pacman_hook (file_id, action_when, action_description)
        VALUES ($1, $2::pacman_hook_action_when, NULLIF($3, ''))
        ON CONFLICT (file_id) DO UPDATE SET
            action_when = EXCLUDED.action_when,
            action_description = EXCLUDED.action_description
        "#,
    )
    .bind(file_id)
    .bind(&action_when)
    .bind(&action_desc)
    .execute(&mut **tx)
    .await?;

    Ok(())
}

async fn classify_symlink_tx<'a>(
    tx: &mut sqlx::Transaction<'a, sqlx::Postgres>,
    pkgroot: &Path,
    file: &str,
    file_id: i32,
) -> Result<()> {
    let path = pkgroot.join(file);

    let link_target = match tokio_fs::read_link(&path).await {
        Ok(target) => {
            let target_str = target.to_str().unwrap_or("");
            if target.starts_with(pkgroot) {
                target
                    .strip_prefix(pkgroot)
                    .unwrap()
                    .to_str()
                    .unwrap_or(target_str)
                    .to_string()
            } else {
                target_str.to_string()
            }
        }
        Err(_) => {
            tracing::warn!("Invalid symlink: {} in {}", file, pkgroot.display());
            return Ok(());
        }
    };

    sqlx::query(
        "INSERT INTO package_file_symlinks (file_id, link_target) VALUES ($1, $2) ON CONFLICT (file_id) DO UPDATE SET link_target = EXCLUDED.link_target"
    )
    .bind(file_id)
    .bind(&link_target)
    .execute(&mut **tx)
    .await?;

    Ok(())
}

fn extract_field(content: &str, field: &str) -> Option<String> {
    let pattern = format!("%{}%", field);
    let lines: Vec<&str> = content.lines().collect();

    for (i, line) in lines.iter().enumerate() {
        if line.trim() == pattern {
            return lines.get(i + 1).map(|s| s.trim().to_string());
        }
    }
    None
}

fn extract_block(content: &str, block: &str) -> String {
    let start_pattern = format!("%{}%", block);
    let mut result = Vec::new();
    let mut in_block = false;

    for line in content.lines() {
        let trimmed = line.trim();
        if trimmed == start_pattern {
            in_block = true;
            continue;
        }
        if in_block {
            if trimmed.starts_with('%') && trimmed.ends_with('%') {
                break;
            }
            result.push(line);
        }
    }

    result.join("\n")
}

fn parse_relation_line(line: &str, block: &str) -> (String, String, String) {
    if block == "OPTDEPENDS" && line.contains(':') {
        let parts: Vec<&str> = line.splitn(2, ':').collect();
        let name_part = parts[0].trim();
        let desc = parts.get(1).map(|s| s.trim()).unwrap_or("").to_string();

        if let Some((name, ver)) = parse_version_constraint(name_part) {
            (name.to_string(), ver.to_string(), desc)
        } else {
            (name_part.to_string(), "".to_string(), desc)
        }
    } else if let Some((name, ver)) = parse_version_constraint(line) {
        (name.to_string(), ver.to_string(), "".to_string())
    } else {
        (line.to_string(), "".to_string(), "".to_string())
    }
}

fn parse_version_constraint(line: &str) -> Option<(&str, &str)> {
    if let Some(pos) = line.find(['<', '>', '=']) {
        let (name, ver) = line.split_at(pos);
        if !name.is_empty() {
            return Some((name.trim(), ver.trim()));
        }
    }
    None
}
