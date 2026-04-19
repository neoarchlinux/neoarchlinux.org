# Installation Guide

Welcome to the NeoArch Linux installation guide. This document walks you through generating an ISO, booting it, and completing the installation.

## Requirements

- 64-bit (x86\_64) CPU
- At least 2 GB RAM (4 GB recommended)
- At least 10 GB of free disk space
- [UEFI firmware](https://wikipedia.org/wiki/UEFI)
- Internet connection

## Step 1 - Generate or download an ISO

Visit the [ISO builder](https://iso.neoarchlinux.org) to generate a custom ISO baked with your preferred settings, or download a generic ISO.

When building a custom ISO you configure:

| Field | Description |
|---|---|
| Hostname | The machine hostname set at install time |
| Language | Locale (e.g. `en_US.UTF-8`) |
| Timezone | Timezone path (e.g. `Europe/Warsaw`) |
| Init system | `openrc`, `systemd`, `runit`, `s6`, or `dinit` |
| Kernels | One or more kernels to install (e.g. `linux`, `linux-lts`) |
| Users | User accounts with optional pre-hashed passwords and admin flag |
| Live packages | Extra packages available in the live environment |
| System packages | Extra packages installed onto the target system |

Passwords provided to the ISO builder are hashed with SHA-512 (`openssl passwd -6`) and stored in `/etc/neoarch-installer.json` inside the image. If a user's password is left blank, the installer will prompt you to set it interactively after the base system is laid down.

## Step 2 - Create a bootable USB

### Linux

```bash
dd if=neoarchlinux.iso of=/dev/sdX bs=4M status=progress oflag=sync
```

Replace `/dev/sdX` with your USB device. Use `lsblk` to identify the correct device.

### Windows

Use [Rufus](https://rufus.ie) or [Balena Etcher](https://etcher.balena.io) in DD mode.

## Step 3 - Boot into the live environment

Reboot and select the USB from your UEFI boot menu (typically F12 or Del at POST). If you do that successfully you should see a screen like this.

![installer-grub](/installer-grub.png)

## Step 4 - Enter the installer

Upon entering the GRUB menu, either wait 5 seconds or press Enter to enter the NeoArch Live System.

There, once the init-system of your choice finishes initialization, you should be greeted with a login screen.

![installer-login-screen](/installer-login-screen.png)

Type `root` and press Enter to enter the bash shell (the root user in the live system has no password).

### Initialize internet connection

Check if you have internet. Type `ping -c 1 neoarchlinux.org` (or any other website, e.g. `8.8.8.8` - Google DNS).

If you see

![ping-successful](/ping-successful.png)

```
1 packets transmitted, 1 received, 0% packet loss
```

everything works, and you can proceed to step 5, but if you see

![ping-unsuccessful](/ping-unsuccessful.png)

`ping: neoarchlinux.org: Temporary failure in name resolution`, you must connect to the internet, e.g. using `nmtui` (easy user interface) or `wpa_supplicant` (harder, manual way).

Once connected to the internet, type `neoarch-installer` and press Enter, you should be greeted with this menu.

![neoarch-installer-welcome](/neoarch-installer-welcome.png)

Press Enter to start the next step.

## Step 5 - Partitioning

The installer presents two partitioning modes.

![neoarch-installer-partitioning-options](/neoarch-installer-partitioning-options.png)

### 5.1 Simple (recommended)

Selects one disk and wipes it entirely. Creates:

- Partition 1 - 1 GB FAT32 EFI system partition → `/boot/efi`
- Partition 2 - Btrfs, remainder of disk, with the following subvolumes:

| Subvolume | Mountpoint |
|---|---|
| `@` | `/` |
| `@home` | `/home` |
| `@var` | `/var` |
| `@log` | `/var/log` |
| `@cache` | `/var/cache` |
| `@snapshots` | `/.snapshots` |

All Btrfs subvolumes are mounted with `compress=zstd,noatime`.

To select the disk, simply move to it using arrow keys and press Enter to confirm. The disks will have their models displayed for simpler selection.

![neoarch-installer-partitioning-disk-selection](/neoarch-installer-partitioning-disk-selection.png)

And then press Enter again to confirm.

THIS WILL WIPE ALL EXISITING DATA FROM THAT DRIVE

![neoarch-installer-partitioning-disk-confirmation](/neoarch-installer-partitioning-disk-confirmation.png)

### 5.2 Manual

Drops you into a shell with `fdisk`, `cfdisk`, `gdisk`, `parted`, `mkfs.*`, and `btrfs` available.

![neoarch-installer-partitioning-manual](/neoarch-installer-partitioning-manual.png)

You must mount at least two partitions:
 - root partition under `/mnt`
 - the EFI partition under `/mnt/boot/efi`

But you can mount more under any subdirectory of `/mnt`.

Type `exit` to return. The installer later reads the resulting mounts and acts accordingly.

The manual partitioning step will not be covered in this guide.

## Step 6 - Installation

When prompted with

`Do you want to proceed with the installation`

Press Enter.

After partitioning, the installer runs the following steps automatically and streams all output to a progress window:

1. Bootstraps the base system using `pacstrap` (systemd) or `basestrap` (Artix-based init systems): `base`, `base-devel`, `linux-firmware`, `grub`, `efibootmgr`, `networkmanager`, `napm`, `neoarch-keyring`, `ntp`, selected kernels, and any extra system packages from the ISO config.
2. For non-systemd init systems, also installs: the init package, `artix-archlinux-support`, `elogind-<init>`, `networkmanager-<init>`, and `ntp-<init>`.
3. Initialises the pacman keyring and populates Arch, Artix, and NeoArch keys.
4. Generates `/etc/fstab`.
5. Sets the timezone, hardware clock, locale.
6. Installs GRUB to the EFI partition (`/boot/efi`), target `x86_64-efi`, bootloader ID `neoarch-grub`.
7. Sets the hostname and `/etc/hosts`.
8. Enables NetworkManager in the init system.
9. Creates user accounts and sets passwords (admin users are added to `wheel`).
10. Enables passwordless `sudo` for the `wheel` group.
11. Writes `/etc/os-release`.

The part of the installation shown below (Retrieving packages) takes the longest, without visible progress, as it needs to download every package from the internet. Allow it to do that and wait for the process to finish.

![installer-installation](/installer-installation.png)

If any command fails, the installer reports the failing command and exits. If that happens and the screen does not clear, press `Ctrl+L` and type `reset`.

## Step 7 - Set passwords for passwordless users

If any user in the ISO config had no password set, the installer prompts you to enter and confirm passwords for those users now.

![neoarch-installer-user-password-msgbox](/neoarch-installer-user-password-msgbox.png)

If you see that screen, press Enter and enter the password for each user, confirming it again in the next screen.

Password selection

![neoarch-installer-password-selection](/neoarch-installer-password-selection.png)

Password confirmation

![neoarch-installer-password-confirmation](/neoarch-installer-password-confirmation.png)

The password will appear as asterisks.

## Step 8 - Reboot

If you did everything correctly you will see the `Installation finished` screen.

![neoarch-installer-finished](/neoarch-installer-finished.png)

Press Enter to exit the installer.

If the screen does not clear, press `Ctrl+L` and type `reset`.

Once the installer exits and reports no errors, remove the USB and reboot, by typing.

```bash
reboot
```

## Final system

After the system reboots, you should see a GRUB menu screen like this:

![installer-installed-grub](/installer-installed-grub.png)

*Note: should be different from the one at Step 3, if not, you still might have your installation medium connected*

Upon entering the GRUB menu, either wait 5 seconds or press Enter to enter your own, new NeoArch Linux system.

The login screen should look simillar to the live one, but have a different hostname (`neoarch-v3` in this example).

![installer-installed-login](/installer-installed-login.png)

Enter the credentials specified either in the ISO builder or the installation step and enjoy your fresh NeoArch Linux system.

*Note: the password will not appear when being typed in*

## Troubleshooting

**The installer fails during `pacstrap`/`basestrap`.**
 - Ensure the live environment has a working internet connection (`ping neoarchlinux.org`).
 - If just one mirror fails, change the mirrors (edit `/etc/pacman.conf`, `/etc/pacman.conf.d/mirrorlist`, `/etc/pacman.conf.d/mirrorlist-arch`)
 - If the ISO is old, the keyring might be stale - [get a new ISO](https://iso.neoarchlinux.org).

**GRUB is not found at boot.**
 - Confirm your firmware is set to UEFI mode (not legacy/BIOS).
 - Confirm Secure Boot is either disabled. The EFI entry is registered as `neoarch-grub`.

**I chose the wrong init system in the ISO but want a different one.**
 - You need to regenerate the ISO with the desired init system selected, as init-system-specific packages are baked into the image and the installer branches on `init_system` from `/etc/neoarch-installer.json`.
