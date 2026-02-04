#!/usr/bin/bash
set -eu

: "${DB_NAME:?DB_NAME not set}"
: "${DB_USER:?DB_USER not set}"
: "${DB_HOST:?DB_HOST not set}"
: "${DB_PASS:?DB_PASS not set}"

psql_safe() {
    PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -Atq "$@"
}

repo="$1"
pkg="$2"
file="$3"
file_id="$4"

BASE_TMP="/tmp/package-updater/package-files"
path="$BASE_TMP/$repo/$pkg/$file"

SYMS="$(objdump -T "$path" 2>/dev/null | awk '{print $NF}')"
ALLSYMS="$(objdump -t "$path" 2>/dev/null | awk '{print $NF}')"
STRINGS="$(strings -a "$path")"

has() {
    echo "$SYMS $ALLSYMS $STRINGS" | grep -qE "$1"
}

psql_safe \
--set=file_id="$file_id" \
--set=is_static="$(file "$path" | grep -q static && echo true || echo false)" \
--set=is_dynamic="$(file "$path" | grep -q dynamic && echo true || echo false)" \
--set=binary_cxx="$(has '__cxa_|std::' && echo true || echo false)" \
--set=binary_go="$(has 'go.buildid|runtime.go' && echo true || echo false)" \
--set=binary_rust="$(has 'rust_eh_|core::' && echo true || echo false)" \
--set=compression_bzip2="$(has 'bz2' && echo true || echo false)" \
--set=compression_lzma="$(has 'lzma|xz' && echo true || echo false)" \
--set=compression_zlib="$(has 'zlib|deflate' && echo true || echo false)" \
--set=directory_create="$(has 'mkdir' && echo true || echo false)" \
--set=directory_read="$(has 'readdir|getdents' && echo true || echo false)" \
--set=directory_remove="$(has 'rmdir' && echo true || echo false)" \
--set=embeds_lua="$(has 'lua_' && echo true || echo false)" \
--set=embeds_python="$(has 'Py_Initialize' && echo true || echo false)" \
--set=execution_deamonizes="$(has 'daemon|setsid' && echo true || echo false)" \
--set=execution_debugs="$(has 'ptrace' && echo true || echo false)" \
--set=execution_does="$(has 'execve|execvp' && echo true || echo false)" \
--set=file_create="$(has 'creat|openat' && echo true || echo false)" \
--set=file_create_temporary="$(has 'mkstemp|tmpfile' && echo true || echo false)" \
--set=file_delete="$(has 'unlink|remove' && echo true || echo false)" \
--set=file_read="$(has 'read|pread' && echo true || echo false)" \
--set=file_rename="$(has 'rename' && echo true || echo false)" \
--set=file_write="$(has 'write|pwrite' && echo true || echo false)" \
--set=kernel_device_interaction="$(has 'ioctl' && echo true || echo false)" \
--set=kernel_event_io="$(has 'epoll|poll|select' && echo true || echo false)" \
--set=kernel_syscall="$(has 'syscall' && echo true || echo false)" \
--set=memory_map="$(has 'mmap' && echo true || echo false)" \
--set=memory_shm="$(has 'shm_' && echo true || echo false)" \
--set=metadata_modify="$(has 'chmod|chown' && echo true || echo false)" \
--set=metadata_query="$(has 'stat|lstat|fstat' && echo true || echo false)" \
--set=networking_dns="$(has 'getaddrinfo|inet_' && echo true || echo false)" \
--set=networking_has="$(has 'socket|connect' && echo true || echo false)" \
--set=networking_http="$(has 'curl_|libcurl' && echo true || echo false)" \
--set=networking_server="$(has 'bind|listen|accept' && echo true || echo false)" \
--set=networking_tls="$(has 'SSL_|TLS_' && echo true || echo false)" \
--set=networking_udp="$(has 'sendto|recvfrom' && echo true || echo false)" \
--set=privilege_changes="$(has 'setuid|capset' && echo true || echo false)" \
--set=supports_audio="$(has 'ogg|mp3|flac' && echo true || echo false)" \
--set=supports_cryptography="$(has 'AES_|SHA|RSA_|EVP_' && echo true || echo false)" \
--set=supports_encoding_conversion="$(has 'iconv' && echo true || echo false)" \
--set=supports_images="$(has 'png_|jpeg_|tiff_' && echo true || echo false)" \
--set=supports_localization="$(has 'setlocale|gettext' && echo true || echo false)" \
--set=supports_unicode="$(has 'mbstowcs|wchar' && echo true || echo false)" \
--set=suspicious_loader_manipulation="$(has 'LD_PRELOAD' && echo true || echo false)" \
--set=suspicious_sandboxing="$(has 'seccomp' && echo true || echo false)" \
--set=suspicious_self_memory_access="$(has '/proc/self/mem' && echo true || echo false)" \
--set=system_env_vars="$(has 'getenv|putenv' && echo true || echo false)" \
--set=system_info_detect="$(has 'uname|sysinfo' && echo true || echo false)" \
--set=system_performance="$(has 'getrusage|clock_gettime' && echo true || echo false)" \
--set=system_user_awareness="$(has 'getuid|getpwuid' && echo true || echo false)" \
--set=thread_sync="$(has 'futex' && echo true || echo false)" \
--set=thread_use="$(has 'pthread_' && echo true || echo false)" <<'SQL'
INSERT INTO package_file_elfbin VALUES (
    :file_id,
    :is_static, :is_dynamic,
    :binary_cxx, :binary_go, :binary_rust,
    :compression_bzip2, :compression_lzma, :compression_zlib,
    :directory_create, :directory_read, :directory_remove,
    :embeds_lua, :embeds_python,
    :execution_deamonizes, :execution_debugs, :execution_does,
    :file_create, :file_create_temporary, :file_delete,
    :file_read, :file_rename, :file_write,
    :kernel_device_interaction, :kernel_event_io, :kernel_syscall,
    :memory_map, :memory_shm,
    :metadata_modify, :metadata_query,
    :networking_dns, :networking_has, :networking_http,
    :networking_server, :networking_tls, :networking_udp,
    :privilege_changes,
    :supports_audio, :supports_cryptography, :supports_encoding_conversion,
    :supports_images, :supports_localization, :supports_unicode,
    :suspicious_loader_manipulation, :suspicious_sandboxing,
    :suspicious_self_memory_access,
    :system_env_vars, :system_info_detect, :system_performance,
    :system_user_awareness,
    :thread_sync, :thread_use
)
ON CONFLICT (file_id) DO NOTHING;
SQL
