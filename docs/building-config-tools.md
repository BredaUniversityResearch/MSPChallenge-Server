# Building the config tools

The config tools (`app:config:split`, `strip`, `merge`, `verify`, `validate` and `list`, see `config-tools-cli.md`) are
built into files that run without the server:

| File | What it is | Needs on the machine that runs it |
|---|---|---|
| `config-tools.phar` | The tools in one file | PHP 8.4 |
| `config-tools-windows-x64.exe`, `config-tools-linux-x64` | The phar with a PHP behind it, in one executable | nothing |
| `config-tools-<system>.zip` | The phar, a PHP and a script that starts it (only when asked, meant for Windows) | nothing |

All of it is made by one script, `bin/build-config-tools`, on a developer machine and in the GitHub workflow
(`.github/workflows/config-tools.yml`).

## What is needed to build

- **PHP 8.4 and composer**, on the machine that builds. This is not Docker: nothing here has to run in a container.
- **Nothing else.** The phar is made by PHP itself (its `Phar` class), no other tool is downloaded.
- For an executable also a **micro.sfx**: static PHP, see below.

## Building

```
php bin/build-config-tools --check             # is the code of the tools self-contained? (offline, a second)
php bin/build-config-tools --version=0.1.0     # build/dist/config-tools.phar, and a try of it
```

`--check` fails when the part of the server that the tools are made of starts to use something that the phar does not
have: a class of the server outside the config code, or a package that is not in `build/config-tools/composer.json`.
Then it says which file and what. There is a test for it (`ConfigToolsBuildTest`), so it fails in a normal test run too.

The build copies the files of the tools to `build/stage`, installs the packages of `build/config-tools/composer.json`
there (only what the tools need, so the phar is about 2 MB), and makes the phar of it. The constraints of those packages
follow the server (`composer-symfony7.4.json` and `composer-symfony8.x.json`: json-schema `^6.12`, Symfony 7.4.18 or 8.1).
For the same packages in every build, run `composer update` once in `build/config-tools` and commit the
`composer.lock` that it makes: the script uses it when it is there (without it every build takes the newest versions that
the constraints allow). Then it **tries what it built**
on a few small configs: it starts, it knows its version, it finds a parent and merges, it validates with the schema (so
the schema is in the phar), and it fails the way it should for a file that is not there. A build that does not pass
that is not a build.

All options: `php bin/build-config-tools --help`. The ones that matter:

| Option | Meaning |
|---|---|
| `--version=X` | What `config-tools --version` says (default: `git describe`, or `dev`) |
| `--output=DIR` | Where the files go (default `build/dist`), with `SHA256SUMS` |
| `--micro=FILE` | Put the phar in this static PHP: an executable |
| `--target=NAME` | For which system the executable is (`windows-x64`, `linux-x64`, `linux-arm64`, `macos-x64`, `macos-arm64`; default: this machine) |
| `--from-phar=FILE` | Do not build the phar: use this one (to put it in the runtime of another system) |
| `--bundle-php=FILE` | A php binary: its folder goes in a zip next to the phar |
| `--vendor=DIR` | A vendor folder that you have, instead of running composer (offline) |

`build/.gitignore` keeps `build/stage`, `build/dist` and `build/cache` out of git, and `build/config-tools/.gitignore` the
`vendor` folder that `composer update` makes there. The root `.gitignore` has to **anchor** the entries of the Symfony
version switch (`/composer.json`, `/composer.lock`, `/symfony.lock`, `/config`): a pattern without the leading slash
matches at every depth, and then the tool's `composer.json` is never committed and the CI fails without it. Commit
`build/config-tools/composer.json` and `composer.lock`; `--check` says so when a `.gitignore` still keeps one of them out.

## An executable: PHP inside the file

A static PHP built with the *micro* SAPI (`micro.sfx`) is a PHP that runs the PHP code that is behind it in the same
file. So the executable is the two files one after the other: `micro.sfx` and `config-tools.phar`. The script does that
(`--micro=micro.sfx`), checks that the file is an executable of the system that the target is for, and tries the result
when it can run on this machine.

**Where micro.sfx comes from.** It is built with [static-php-cli](https://static-php.dev) (`spc`), for one system at a
time, with the extensions the tools need: `phar`, `mbstring` and `filter` (the JSON schema library calls `filter_var`; the
rest the packages do themselves, with polyfills).

- **From the workflow** (the easiest): the job `executable` builds it for Linux and Windows, and puts the phar in it.
  The files are in the run (Artifacts), and in the release for a tag.
- **By hand**: download `spc` for your system from `https://dl.static-php.dev/static-php-cli/spc-bin/nightly/`, then

  ```
  spc doctor --auto-fix
  spc download --for-extensions="phar,mbstring,filter" --with-php=8.4 --prefer-pre-built
  spc build "phar,mbstring,filter" --build-micro
  php bin/build-config-tools --micro=buildroot/bin/micro.sfx
  ```

  On **Windows** that needs Visual Studio 2022 (with the C++ tools) and Git; a build is some minutes the first time.
  Run `spc` in **PowerShell or cmd, not in Git Bash**: under Git Bash `tar` is Git's GNU tar, that reads `D:\...` as a remote
  host and fails extracting the PHP sources (the workflow runs that step in PowerShell, with `C:\Windows\System32` first on
  the `PATH` so that the `tar` of Windows is used).
  `spc` (2.8.6) does not recognise Visual Studio 2026 yet (`spc doctor` says "Visual Studio not installed"): that is why
  the workflow uses the runner `windows-2022` and not `windows-latest`, which has VS 2026 since June 2026.
  On **Linux and macOS** it needs the usual compilers (`spc doctor --auto-fix` installs what is missing).
- Pre-built binaries are published by the static-php-cli project, but for Windows only a small set is documented, so it
  is not known that one with `phar` is among them. Check before you rely on it.

**Why not Docker?** It is not needed: the phar and the concatenation are made anywhere PHP runs, and the workflow builds the static
PHP on a Linux and a Windows runner. Docker would only help to build the *Linux* PHP on a machine that is not Linux, or
to build it fully static on musl (the `spc` project has an Alpine image for that). A Windows executable can not be
built in a Linux container: that is built on Windows.

**The zip instead.** If the static PHP gives trouble, `--bundle-php=C:\php\php.exe` (the PHP from windows.php.net, any
8.4 build) makes `config-tools-windows-x64.zip` with the phar, the folder of that PHP, and `config-tools.cmd` that starts
it. It needs the Visual C++ runtime on the machine, that a developer machine nearly always has.

## Releasing

```
php bin/release-config-tools
```

The script asks what it needs and shows what it will do before it does it. It needs only `git` (the `gh` command is not
needed). A release is a tag `config-tools-v<version>` that is pushed: the workflow then builds and publishes it.

1. It checks that the workflow is in the last commit, runs `php bin/build-config-tools --check`, and tells you about changes
   that are not committed (they are not in a release) and commits that are not pushed (it offers to push them).
2. It asks for the version number (the highest one that exists is suggested) and the kind:
   - **pre-release**: `config-tools-v6.0.5-pre`, for trying a build. GitHub marks it as a pre-release and not as "Latest".
   - **official release**: `config-tools-v6.0.5`.
3. It makes the tag on the last commit and pushes it, and says where to look: the Actions page, and the release.

To release officially after a pre-release, run it again and choose the official release of the same number; it then offers to
remove the pre-release (menu item 2). A tag can only be used once: if the version has been released already, the script
asks to remove the old one first. Removing is done in two steps, because without `gh` the GitHub release can only be deleted
on the web: the script gives the address, waits, and then removes the tag with `git push --delete`. With `gh` installed it
does both. `--dry-run` shows the git commands and runs none that changes something. `--help` lists the options for a run
without questions (`--version=6.0.5 --pre --yes`).

## The workflow

`.github/workflows/config-tools.yml`:

- **A pull request** that touches the tools: `--check`, and a build with its try. Nothing is released.
- **A tag `config-tools-v1.2.3`**: builds, tries, and makes a GitHub release with `config-tools.phar`, the executables and
  `SHA256SUMS`. The tag has its own prefix because the repository has releases of the server too, and the number is the
  version of the platform that the tools belong to (`config-tools-v6.0.5`). A release of the tools is never marked
  "Latest" (that is for the server: `/releases/latest` must keep giving the server), and a version with a `-`
  (`6.0.5-rc1`) is a pre-release. Whoever wants the tools lists the releases and takes the newest `config-tools-v*`
  that has the file it needs: see R-INT-4 of the config editor requirements.
- **Run workflow** (by hand): the files are in the run.
- The phar is built once (job `phar`). The executables are separate jobs (`executable`) that can fail without stopping
  the release of the phar: building a static PHP depends on other software.

## What has been tried, and what has not

Tried (on Linux, with a PHP 8.3 and a stand-in for composer, so the build and not the PHP 8.4 syntax): the check (also
that it finds a class of the server and a package that is not in the tools), building the phar (459 files, 2.2 MB),
the version in the phar, the try of the phar (it found the schema in the phar, which was missing at first: Symfony Console
reads files other than php files from its own folder), putting a phar behind an executable and refusing a file that is
not one, and the zip.

**Not tried, so expect to iterate:** composer installing the real packages (the constraints follow the server, see
above, but no lock file has been made yet), a real
`micro.sfx` with this phar behind it (static-php-cli warns that a phar does not always run in micro), the Windows build of
the static PHP, and the workflow itself.

## When something goes wrong

- **`composer install failed`**: composer is needed to build. Or use `--vendor=DIR` with packages that you have.
- **`Could not remove ...` at the end** (Windows): a file of `build/stage` was locked for a moment (a virus scanner or the
  indexer). The build is fine; remove the folder by hand, or exclude `build` from the scanner.
- **`validate does not have the schema`** in the try: the schema (`src/Domain/SessionConfigJSONSchema.json`) is not in
  the phar.
- **The executable is flagged by an antivirus**: unsigned programs that unpack themselves are. Sign it, or use the zip.
- **Out of memory**: the tools allow 1 GB; `CONFIG_TOOLS_MEMORY_LIMIT=2G` changes it for one run.
