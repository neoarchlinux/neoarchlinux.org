import asyncio
import json
import uuid
import websockets
import datetime
import subprocess
import os
import time
import shlex

out_dir_base = f'/app/isos'

# cmds

def _cmd_impl(c, check=True):
    proc = subprocess.run(
        ["bash", "-c", c],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )

    if check and proc.returncode != 0:
        raise RuntimeError(
            "Command failed\n"
            f"command: {c}\n"
            f"exit code: {proc.returncode}\n"
            f"stderr:\n{proc.stderr}"
        )

    return proc

def _cmd(c, check = True):
    return _cmd_impl(c, check = check).stdout

async def _cmd_async(c, check = True):
    return await asyncio.to_thread(_cmd, c, check)

def _cmd_ok(c, check = True):
    return _cmd_impl(c, check = check).returncode == 0

async def _cmd_ok_async(c, check = True):
    return await asyncio.to_thread(_cmd_ok, c, check)

# ~cmds

async def handle(ws):
    async def send(status, phase):
        print(f"[{build_id}] {status}")

        msg = {
            "status": status,
            "phase": phase,
            "phase_count": 12,
        }

        await ws.send(json.dumps(msg))
    # ~send

    try:
        # request
        raw = await ws.recv()
        init = json.loads(raw)
        params = init.get("params")

        # parse request
        hostname = params['hostname'] # TODO: set hostname
        language = params['language'] # TODO: set language
        # TODO: users
        # TODO: additional packages
        # TODO: kernel
        init_system = params['init_system']
        # TODO: enable_testing
        # TODO: parallel_downloads

        # init vars
        build_id = str(uuid.uuid4())
        print(f"[{build_id}] Build started")
        out_dir = f"{out_dir_base}/{build_id}"
        iso_name = "neoarchlinux"
        iso_uuid = f'{build_id}-{datetime.datetime.now().strftime("%Y-%m-%d-%H-%M-%S")}'
        iso_label = f"NEOARCH_{datetime.datetime.now().strftime('%Y%m')}"
        iso_publisher = "NeoArch Linux"
        iso_application = "NeoArch Linux Live + Installer"
        iso_version = datetime.datetime.now().strftime("%Y.%m.%d")
        install_dir = "neoarch"
        bootmodes = ['uefi.grub']
        pacman_conf = "/iso/profile/pacman.conf"
        airootfs_image_type = "erofs"
        airootfs_image_tool_options = ['-zlzma,109', '-E', 'ztailpacking']
        bootstrap_tarball_compression = ['zstd', '-c', '-T0', '--long', '-19']

        packages = [
            'arch-install-scripts', # ???
            'base',
            'base-devel',
            'efibootmgr',
            'grub',
            'mkinitcpio',
            'mkinitcpio-archiso', # ???
            'napm',
            'neoarch-keyring',
            'networkmanager',
            'open-vm-tools',
            'os-prober',
            'qemu-guest-agent',
            'syslinux',
            'virtualbox-guest-utils-nox'
        ]

        if init_system == 'systemd':
            pass
        else:
            packages.append('artix-archlinux-support')
            packages.append(init_system)
            packages.append(f'elogind-{init_system}')
            packages.append(f'networkmanager-{init_system}')

        # build iso
        work_dir = f'/tmp/iso-{build_id}'
        image_name = f"{hostname}.iso"
        efibootimg = f"{work_dir}/efiboot.img"
        
        # build iso base
        pacstrap_dir = f"{work_dir}/airootfs"
        isofs_dir = f"{work_dir}/iso"

        # make work dir
        await _cmd_async(f'install -d -- "{work_dir}"')

        # make pacman conf
        await _cmd_async(f"""
            pacman-conf --config {pacman_conf} | sed '
            /CacheDir/d
            /DBPath/d
            /HookDir/d
            /LogFile/d
            /RootDir/d
            /\\[options\\]/a CacheDir = /var/cache/pacman/pkg
            /\\[options\\]/a HookDir = {pacstrap_dir}/etc/pacman.d/hooks/
            ' > "{work_dir}/iso.pacman.conf"
            """)

        # TODO: export gpg publickey
        
        # make custom airootfs
        await send("Copying files", 1)
        passwd = []
        await _cmd_async(f'install -d -m 0755 -o 0 -g 0 -- "{pacstrap_dir}"')
        await _cmd_async(f'cp -af --no-preserve=ownership,mode -- "/iso/profile/airootfs/." "{pacstrap_dir}"')
        await _cmd_async(f'chown -fhR -- "0:0" "{pacstrap_dir}/etc/shadow"')
        await _cmd_async(f'chmod -f -- "400" "{pacstrap_dir}/etc/shadow"')

        # make packages
        await send("Installing ISO packages", 2)
        await _cmd_async(f'env -u TMPDIR pacstrap -C "{work_dir}/iso.pacman.conf" -c -G -M -- "{pacstrap_dir}" {' '.join(packages)} > /dev/null')
        
        # copy correct pacman conf
        await _cmd_async(f'install -m 0644 -- "{pacman_conf}" "{pacstrap_dir}/etc/pacman.conf"')

        # make version
        await send("Creating version files", 3)
        search_filename = f"/boot/{iso_uuid}.uuid"
        await _cmd_async(f'rm -f -- "{pacstrap_dir}/version"')
        await _cmd_async(f'printf \'%s\\n\' "{iso_version}" > "{pacstrap_dir}/version"')
        await _cmd_async(f'install -d -m 0755 -- "{isofs_dir}/{install_dir}"')
        await _cmd_async(f'printf \'%s\\n\' "{iso_version}" > "{isofs_dir}/{install_dir}/version"')
        grubenv_path = f"{isofs_dir}/{install_dir}/grubenv"
        await _cmd_async(f'rm -f -- "{grubenv_path}"')
        grubenv_content = (
            "# GRUB Environment Block\n"
            f"NAME={iso_name}\n"
            f"VERSION={iso_version}\n"
            f"ARCHISO_LABEL={iso_label}\n"
            f"INSTALL_DIR={install_dir}\n"
            f"ARCH=x86_64\n"
            f"ARCHISO_SEARCH_FILENAME={search_filename}\n"
            + "#" * 1024
        )
        with open(grubenv_path, "w", encoding="utf-8") as f:
            f.write(grubenv_content[:1024])
        await _cmd_async(f'install -d -m 755 -- "{isofs_dir}/boot"')
        await _cmd_async(f': > "{isofs_dir}{search_filename}"')
        _os_release = await _cmd_async(f'realpath -- "{pacstrap_dir}/etc/os-release"')
        await _cmd_async(f'printf \'IMAGE_ID=%s\\nIMAGE_VERSION=%s\\n\' "{iso_name}" "{iso_version}" >> "{_os_release}"')
        await _cmd_async(f'touch -m -d"@{datetime.datetime.now().strftime("%s")}" -- "{pacstrap_dir}/usr/lib/clock-epoch"')
        await _cmd_async(f'touch "{isofs_dir}{search_filename}"')

        # make package listing
        await send("Creating package listing", 4)
        await _cmd_async(f'install -d -m 0755 -- "{isofs_dir}/{install_dir}"')
        await _cmd_async(f'pacman -Q --sysroot "{pacstrap_dir}" > "{isofs_dir}/{install_dir}/pkglist.x86_64.txt"')

        # TODO: check if initramfs has ucode

        # make boot on iso9660
        await send("Perparing the ISO file system", 5)
        await _cmd_async(f'install -d -m 0755 -- "{isofs_dir}/{install_dir}/boot"')
        await _cmd_async(f'install -m 0644 -- "{pacstrap_dir}/boot/initramfs-"*".img" "{isofs_dir}/{install_dir}/boot/"')
        await _cmd_async(f'install -m 0644 -- "{pacstrap_dir}/boot/vmlinuz-"* "{isofs_dir}/{install_dir}/boot/"')

        # TODO: if need external ucodes, install
        
        # make grub
        await send("Setting up GRUB", 6)
        grub_target = 'x86_64-efi'
        await _cmd_async(f'install -d -- "{work_dir}/grub"')
        
        for _cfg in [
            'grub.cfg',
            'loopback.cfg',
        ]:
            await _cmd_async(f'''
                sed "s|%ARCHISO_LABEL%|{iso_label}|g;
                        s|%ARCHISO_UUID%|{iso_uuid}|g;
                        s|%INSTALL_DIR%|{install_dir}|g;
                        s|%ARCHISO_SEARCH_FILENAME%|{search_filename}|g" \\
                        "/iso/profile/grub/{_cfg}" > "{work_dir}/grub/{_cfg}"
            ''')

        grubembedcfg = r'''
if ! [ -d "$cmdpath" ]; then
if regexp --set=1:archiso_bootdevice '^\(([^)]+)\)\/?[Ee][Ff][Ii]\/[Bb][Oo][Oo][Tt]\/?$' "${cmdpath}"; then
    set cmdpath="(${archiso_bootdevice})/EFI/BOOT"
    set ARCHISO_HINT="${archiso_bootdevice}"
fi
fi

if [ -z "${ARCHISO_HINT}" ]; then
regexp --set=1:ARCHISO_HINT '^\(([^)]+)\)' "${cmdpath}"
fi

if search --no-floppy --set=archiso_device --file '%ARCHISO_SEARCH_FILENAME%' --hint "${ARCHISO_HINT}"; then
set ARCHISO_HINT="${archiso_device}"
if probe --set ARCHISO_UUID --fs-uuid "${ARCHISO_HINT}"; then
    export ARCHISO_UUID
fi
else
echo "Could not find a volume with a '%ARCHISO_SEARCH_FILENAME%' file on it!"
fi

if [ "${ARCHISO_HINT}" == 'memdisk' -o -z "${ARCHISO_HINT}" ]; then
echo 'Could not find the ISO volume!'
elif [ -e "(${ARCHISO_HINT})/boot/grub/grub.cfg" ]; then
export ARCHISO_HINT
set root="${ARCHISO_HINT}"
configfile "(${ARCHISO_HINT})/boot/grub/grub.cfg"
else
echo "File '(${ARCHISO_HINT})/boot/grub/grub.cfg' not found!"
fi
        '''.strip()

        grubembedcfg = grubembedcfg.replace(
            "%ARCHISO_SEARCH_FILENAME%",
            search_filename,
        )

        with open(f"{work_dir}/grub-embed.cfg", "w", encoding="utf-8") as f:
            f.write(grubembedcfg)
            f.write("\n")

        header = (
            "# GRUB Environment Block\n"
            f"NAME={iso_name}\n"
            f"VERSION={iso_version}\n"
            f"ARCHISO_LABEL={iso_label}\n"
            f"INSTALL_DIR={install_dir}\n"
            f"ARCH=x86_64\n"
            f"ARCHISO_SEARCH_FILENAME={search_filename}\n"
        )

        padding = "#" * 1024

        grubenv = (header + padding)[:1024]

        with open(f"{work_dir}/grub/grubenv", "w", encoding="utf-8") as f:
            f.write(grubenv)

        grubmodules = [
            "all_video", "at_keyboard", "boot", "btrfs", "cat", "chain",
            "configfile", "echo", "efifwsetup", "efinet", "exfat", "ext2",
            "f2fs", "fat", "font", "gfxmenu", "gfxterm", "gzio", "halt",
            "hfsplus", "iso9660", "jpeg", "keylayouts", "linux", "loadenv",
            "loopback", "lsefi", "lsefimmap", "minicmd", "normal", "ntfs",
            "ntfscomp", "part_apple", "part_gpt", "part_msdos", "png",
            "read", "reboot", "regexp", "search", "search_fs_file",
            "search_fs_uuid", "search_label", "serial", "sleep", "tpm",
            "udf", "usb", "usbserial_common", "usbserial_ftdi",
            "usbserial_pl2303", "usbserial_usbdebug", "video", "xfs", "zstd",
        ]

        modules_arg = " ".join(grubmodules)

        await _cmd_async(f'''
            grub-mkstandalone -O "{grub_target}" \
                --modules="{modules_arg}" \
                --locales="en@quot" \
                --themes="" \
                --sbat=/usr/share/grub/sbat.csv \
                --disable-shim-lock \
                -o "{work_dir}/BOOTx64.EFI" \
                "boot/grub/grub.cfg={work_dir}/grub-embed.cfg"
        ''')

        efiboot_files = []

        efiboot_files.extend([
            f"{work_dir}/BOOTx64.EFI",
            f"{pacstrap_dir}/usr/share/edk2-shell/x64/Shell_Full.efi",
        ])

        await _cmd_async(f'''
            grub-mkstandalone -O i386-efi \
                --modules="{modules_arg}" \
                --locales="en@quot" \
                --themes="" \
                --sbat=/usr/share/grub/sbat.csv \
                --disable-shim-lock \
                -o "{work_dir}/BOOTIA32.EFI" \
                "boot/grub/grub.cfg={work_dir}/grub-embed.cfg"
        ''')

        efiboot_files.extend([
            f"{work_dir}/BOOTIA32.EFI",
            f"{pacstrap_dir}/usr/share/edk2-shell/ia32/Shell_Full.efi",
        ])

        imgsize_kib = 0
        mkfs_fat_opts = ["-C", "-n", "ARCHISO_EFI"]
        files = " ".join(f'"{f}"' for f in efiboot_files)

        imgsize_cmd = (
            f"du -bcs -- {files} 2>/dev/null | "
            "awk 'function ceil(x){return int(x)+(x>int(x))} "
            "function byte_to_kib(x){return x/1024} "
            "function mib_to_kib(x){return x*1024} "
            "END{print mib_to_kib(ceil((byte_to_kib($1)+8192)/1024))}'"
        )
        imgsize_kib = int((await _cmd_async(imgsize_cmd)).strip())

        if imgsize_kib >= 36864:
            mkfs_fat_opts.extend(["-F", "32"])

        await _cmd_async(f'rm -f -- "{efibootimg}"')

        await send(f"Creating FAT image", 7)

        opts = " ".join(mkfs_fat_opts)

        await _cmd_async(f'''
            mkfs.fat {opts} "{efibootimg}" "{imgsize_kib}"
        ''')

        await _cmd_async(f'''
            mmd -i "{efibootimg}" ::/EFI ::/EFI/BOOT
        ''')

        await _cmd_async(f'install -d -m 0755 -- "{isofs_dir}/EFI/BOOT"')

        await _cmd_async(
            f'mcopy -i "{efibootimg}" '
            f'"{work_dir}/BOOTx64.EFI" '
            f'"::/EFI/BOOT/BOOTx64.EFI"'
        )

        await _cmd_async(
            f'install -m 0644 -- '
            f'"{work_dir}/BOOTx64.EFI" '
            f'"{isofs_dir}/EFI/BOOT/BOOTx64.EFI"'
        )

        await _cmd_async(
            f'mcopy -i "{efibootimg}" '
            f'"{work_dir}/BOOTIA32.EFI" '
            f'"::/EFI/BOOT/BOOTIA32.EFI"'
        )
        
        await _cmd_async(
            f'install -m 0644 -- '
            f'"{work_dir}/BOOTIA32.EFI" '
            f'"{isofs_dir}/EFI/BOOT/BOOTIA32.EFI"'
        )

        files_to_copy = [f'{work_dir}/grub/*']

        if await _cmd_ok_async(
            f'compgen -G "/iso/profile/grub/!(*.cfg)"',
            check = False
        ):
            files_to_copy.append(f'/iso/profile/grub/!(*.cfg)')

        await _cmd_async(f'install -d -m 0755 -- "{isofs_dir}/boot/grub"')

        files = " ".join(f'{f}' for f in files_to_copy)

        await _cmd_async(
            f'cp -r --remove-destination -- {files} "{isofs_dir}/boot/grub/"'
        )

        shell_path = f'{pacstrap_dir}/usr/share/edk2-shell/x64/Shell_Full.efi'

        if await _cmd_ok_async(
            f'test -e "{shell_path}"',
            check = False
        ):
            await _cmd_async(
                f'mcopy -i "{efibootimg}" '
                f'"{shell_path}" '
                f'"::/shellx64.efi"'
            )

            await _cmd_async(
                f'install -m 0644 -- "{shell_path}" '
                f'"{isofs_dir}/shellx64.efi"'
            )

        ia32_shell = (
            f'{pacstrap_dir}/usr/share/edk2-shell/ia32/Shell_Full.efi'
        )

        if await _cmd_ok_async(
            f'test -e "{ia32_shell}"',
            check = False
        ):
            await _cmd_async(
                f'mcopy -i "{efibootimg}" '
                f'"{ia32_shell}" "::/shellia32.efi"'
            )

            await _cmd_async(
                f'install -m 0644 -- "{ia32_shell}" '
                f'"{isofs_dir}/shellia32.efi"'
            )

        memtest = f'{pacstrap_dir}/boot/memtest86+/memtest.efi'

        if await _cmd_ok_async(
            f'test -e "{memtest}"',
            check = False
        ):
            await _cmd_async(
                f'install -d -m 0755 -- '
                f'"{isofs_dir}/boot/memtest86+/"'
            )

            await _cmd_async(
                f'install -m 0644 -- "{memtest}" '
                f'"{isofs_dir}/boot/memtest86+/memtest.efi"'
            )

            await _cmd_async(
                f'install -m 0644 -- '
                f'"{pacstrap_dir}/usr/share/licenses/spdx/GPL-2.0-only.txt" '
                f'"{isofs_dir}/boot/memtest86+/LICENSE"'
            )

        await _cmd_async(f'install -d -m 0755 -- "{isofs_dir}/boot/grub"')

        await _cmd_async(
            f'''
            printf '%.1024s' "$(
                printf '# GRUB Environment Block\\nNAME=%s\\nVERSION=%s\\nARCHISO_LABEL=%s\\nINSTALL_DIR=%s\\nARCH=%s\\nARCHISO_SEARCH_FILENAME=%s\\n%s' \
                    "{iso_name}" \
                    "{iso_version}" \
                    "{iso_label}" \
                    "{install_dir}" \
                    "x86_64" \
                    "{search_filename}" \
                    "$(printf '%0.1s' "#"{{1..1024}})"
            )" > "{isofs_dir}/boot/grub/grubenv"
            '''
        )

        loopback_src = f"/iso/profile/grub/loopback.cfg"
        loopback_dst = f"{isofs_dir}/boot/grub/loopback.cfg"

        if await _cmd_ok_async(
            f'test -e "{loopback_src}"',
            check = False
        ):
            await _cmd_async(
                f'''
                sed "
                    s|%ARCHISO_LABEL%|{iso_label}|g;
                    s|%ARCHISO_UUID%|{iso_uuid}|g;
                    s|%INSTALL_DIR%|{install_dir}|g;
                    s|%ARCHISO_SEARCH_FILENAME%|{search_filename}|g
                " "{loopback_src}" > "{loopback_dst}"
                '''
            )

        # clean pacstrap
        await send("Removing unnecessary files", 8)
        await _cmd_async(f'find "{pacstrap_dir}/boot" -mindepth 1 -delete')
        await _cmd_async(f'find "{pacstrap_dir}/var/lib/pacman" -maxdepth 1 -type f -delete')
        await _cmd_async(f'find "{pacstrap_dir}/var/lib/pacman/sync" -delete')
        await _cmd_async(f'find "{pacstrap_dir}/var/cache/pacman/pkg" -type f -delete')
        await _cmd_async(f'find "{pacstrap_dir}/var/log" -type f -delete')
        await _cmd_async(f'find "{pacstrap_dir}/var/tmp" -mindepth 1 -delete')
        await _cmd_async(f'find "{work_dir}" \\( -name \'*.pacnew\' -o -name \'*.pacsave\' -o -name \'*.pacorig\' \\) -delete')
        await _cmd_async(f'rm -f -- "{pacstrap_dir}/etc/machine-id"')
        await _cmd_async(f'echo "uninitialized" > "{pacstrap_dir}/etc/machine-id"')
        
        # make airootfs erofs
        await send("Creating the ISO filesystem", 9)
        await _cmd_async(f'install -d -m 0755 -- "{isofs_dir}/{install_dir}/x86_64"')
        image_path = f"{isofs_dir}/{install_dir}/x86_64/airootfs.erofs"
        await _cmd_async(f'rm -f -- "{image_path}"')
        opts = " ".join(airootfs_image_tool_options)
        await _cmd_async(f'mkfs.erofs -U 00000000-0000-0000-0000-000000000000 {opts} -- "{image_path}" "{pacstrap_dir}"')
        
        # make checksum
        await send("Creating the ISO checksum", 10)
        wd = f'{isofs_dir}/{install_dir}/x86_64'
        await _cmd_async(f'sha512sum "{wd}/airootfs.erofs" > "{wd}/airootfs.sha512"')

        # TODO: gpg and cert

        # build iso image
        await send("Building the ISO", 11)
        await _cmd_async(f'rm -rf "{work_dir}/x86_64/airootfs"')
        xorriso_options = ['-no_rc']
        xorrisofs_options = [
            '-partition_offset', '16',
            '-append_partition', '2', 'C12A7328-F81F-11D2-BA4B-00A0C93EC93B', efibootimg,
            '-appended_part_as_gpt',
            '-eltorito-alt-boot',
            '-e', '--interval:appended_partition_2:all::',
            '-no-emul-boot'
        ]

        if (int(await _cmd_async(f'du -s --apparent-size -B1M "{isofs_dir}/" | awk \'{{ print $1 }}\'')) > 900):
            xorrisofs_options.append('-no-pad')
        
        await _cmd_async(f'rm -rf "{work_dir}/{image_name}"')

        xorriso_opts = " ".join(xorriso_options)
        xorrisofs_opts = " ".join(xorrisofs_options)
        await _cmd_async(
            f'xorriso {xorriso_opts} -as mkisofs'
                +  ' -iso-level 3'
                +  ' -full-iso9660-filenames'
                +  ' -joliet'
                +  ' -joliet-long'
                +  ' -rational-rock'
                + f' -volid "{iso_label}"'
                + f' -appid "{iso_application}"'
                + f' -publisher "{iso_publisher}"'
                + f' -preparer "prepared by https://iso.neoarchlinux.org"'
                + f' {xorrisofs_opts}'
                + f' -output "{work_dir}/{image_name}"'
                + f' "{isofs_dir}/"'
        )

        await _cmd_async(f'install -d -- "{out_dir}"')
        await _cmd_async(f'mv "{work_dir}/{image_name}" "{out_dir}/{image_name}"')

        await send("Removing unnecessary files", 12)
        await _cmd_async(f'rm -rf "{work_dir}/"')

        await ws.send(json.dumps({
            "status": "Done",
            "download_url": f"/download/{build_id}/{image_name}"
        }))
    except Exception as e:
        print(f"[{build_id}] An error occured: {e}")

        await ws.send(json.dumps({
            "status": "Error"
        }))

    await ws.close()

    print(f"[{build_id}] Build finished")
# ~handle

async def cleanup_old_files_loop(out_dir_base, max_age_seconds, interval):
    while True:
        await _cmd_async(f"find {out_dir_base} -mindepth 1 -maxdepth 1 -type d -mmin +120 -exec rm -rf {{}} +")
        await asyncio.sleep(interval)

async def main():
    asyncio.create_task(
        cleanup_old_files_loop(out_dir_base, 3600, 600)
    )

    port = 4150

    async with websockets.serve(handle, "0.0.0.0", port):
        print(f"iso-builder ws listening on :{port}")
        await asyncio.Future()

asyncio.run(main())
