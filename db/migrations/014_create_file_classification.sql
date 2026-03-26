CREATE TYPE package_file_type AS ENUM (
    'EMPTY',
    'ELFBIN',
    'ELFLIB',
    'CONF',
    'SCRIPT',
    'PACMAN_HOOK',
    'TEXT',
    'SYMLINK',
    'DATA',
    'OTHER'
);

CREATE TABLE package_files (
    id SERIAL PRIMARY KEY,
    package_id INT NOT NULL REFERENCES package_meta(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,
    file_type package_file_type NOT NULL,
    file_mode INT NOT NULL,
    file_size INT NOT NULL,
    UNIQUE (package_id, file_path)
);

CREATE TABLE package_file_elfbin (
    file_id INT PRIMARY KEY REFERENCES package_files(id) ON DELETE CASCADE,

    is_static BOOLEAN NOT NULL,
    is_dynamic BOOLEAN NOT NULL,

    binary_cxx BOOLEAN NOT NULL,
    binary_go BOOLEAN NOT NULL,
    binary_rust BOOLEAN NOT NULL,

    compression_bzip2 BOOLEAN NOT NULL,
    compression_lzma BOOLEAN NOT NULL,
    compression_zlib BOOLEAN NOT NULL,
    
    directory_create BOOLEAN NOT NULL,
    directory_read BOOLEAN NOT NULL,
    directory_remove BOOLEAN NOT NULL,
    
    embeds_lua BOOLEAN NOT NULL,
    embeds_python BOOLEAN NOT NULL,
    
    execution_deamonizes BOOLEAN NOT NULL,
    execution_debugs BOOLEAN NOT NULL,
    execution_does BOOLEAN NOT NULL,
    
    file_create BOOLEAN NOT NULL,
    file_create_temporary BOOLEAN NOT NULL,
    file_delete BOOLEAN NOT NULL,
    file_read BOOLEAN NOT NULL,
    file_rename BOOLEAN NOT NULL,
    file_write BOOLEAN NOT NULL,
    
    kernel_device_interaction BOOLEAN NOT NULL,
    kernel_event_io BOOLEAN NOT NULL,
    kernel_syscall BOOLEAN NOT NULL,
    
    memory_map BOOLEAN NOT NULL,
    memory_shm BOOLEAN NOT NULL,
    
    metadata_modify BOOLEAN NOT NULL,
    metadata_query BOOLEAN NOT NULL,
    
    networking_dns BOOLEAN NOT NULL,
    networking_has BOOLEAN NOT NULL,
    networking_http BOOLEAN NOT NULL,
    networking_server BOOLEAN NOT NULL,
    networking_tls BOOLEAN NOT NULL,
    networking_udp BOOLEAN NOT NULL,
    
    privilege_changes BOOLEAN NOT NULL,
    
    supports_audio BOOLEAN NOT NULL,
    supports_cryptography BOOLEAN NOT NULL,
    supports_encoding_conversion BOOLEAN NOT NULL,
    supports_images BOOLEAN NOT NULL,
    supports_localization BOOLEAN NOT NULL,
    supports_unicode BOOLEAN NOT NULL,
    
    suspicious_loader_manipulation BOOLEAN NOT NULL,
    suspicious_sandboxing BOOLEAN NOT NULL,
    suspicious_self_memory_access BOOLEAN NOT NULL,
    
    system_env_vars BOOLEAN NOT NULL,
    system_info_detect BOOLEAN NOT NULL,
    system_performance BOOLEAN NOT NULL,
    system_user_awareness BOOLEAN NOT NULL,
    
    thread_sync BOOLEAN NOT NULL,
    thread_use BOOLEAN NOT NULL
);

CREATE TABLE package_file_conf_users (
    file_id INT PRIMARY KEY REFERENCES package_files(id) ON DELETE CASCADE,
    conf_user TEXT NOT NULL
);

CREATE TABLE package_file_script (
    file_id INT PRIMARY KEY REFERENCES package_files(id) ON DELETE CASCADE,
    script_executable TEXT
);

CREATE TYPE pacman_hook_trigger_type AS ENUM (
    'Path',
    'Package'
);

CREATE TABLE package_file_pacman_hook_triggers (
    id SERIAL PRIMARY KEY,
    file_id INT REFERENCES package_files(id) ON DELETE CASCADE,
    trigger_type pacman_hook_trigger_type NOT NULL,
    trigger_on_install BOOLEAN NOT NULL,
    trigger_on_upgrade BOOLEAN NOT NULL,
    trigger_on_remove BOOLEAN NOT NULL,
    UNIQUE (file_id, trigger_type, trigger_on_install, trigger_on_upgrade, trigger_on_remove)
);

CREATE TYPE pacman_hook_action_when AS ENUM (
    'PreTransaction',
    'PostTransaction'
);

CREATE TABLE package_file_pacman_hook (
    file_id INT PRIMARY KEY REFERENCES package_files(id) ON DELETE CASCADE,
    action_description TEXT,
    action_when pacman_hook_action_when NOT NULL
);

CREATE TABLE pacman_hook_trigger_targets (
    id SERIAL PRIMARY KEY,
    trigger_id INT NOT NULL REFERENCES package_file_pacman_hook_triggers(id) ON DELETE CASCADE,
    trigger_target TEXT NOT NULL,
    UNIQUE (trigger_id, trigger_target)
);

CREATE TABLE package_file_symlinks (
    file_id INT PRIMARY KEY REFERENCES package_files(id) ON DELETE CASCADE,
    link_target TEXT NOT NULL
);
