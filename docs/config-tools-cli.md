# Config tools: command line reference

The config tools turn configs with parents into final configs, check them, and keep them small. They exist as the
`app:config:*` commands of the server (`php bin/console app:config:...`) and on their own, without the server
(`php bin/config-tools app:config:...`). The commands, options and output are the same.

This page is for programs and people that use the tools, such as the config editor and the CI of a repository with
configs. How configs work (parents, merging, what becomes generic) is described in *Config simplification:
implementation design*.

## Running the tools

```
php bin/config-tools <command> [arguments] [options]
php bin/console <command> [arguments] [options]       # in the server
```

- **PHP 8.4 or later** is needed. `bin/config-tools` says so and exits with 1 on an older PHP.
- `config-tools` has no kernel, no database and no container. It needs the code of `src/Domain/Config` and the
  commands, and `symfony/console`, `symfony/filesystem`, `symfony/finder` and `justinrainbow/json-schema`.
- `verify` runs `git` (unless `--original-dir` is used).
- **The config root** is the folder with the configs, `--dir`. In `config-tools` it defaults to the folder the tool is
  started in. In the server it defaults to `ServerManager/configfiles`. Configs and generic configs (parents) can be
  anywhere below it. Relative paths of files and of `--output` are relative to the folder the command is started in.
- `--version` and `list` (Symfony's own command that lists the commands) work as usual.

## Commands

| Command | What it does |
|---|---|
| `app:config:list` | Lists the configs and the parents below the root, who uses whom, and what is wrong with it |
| `app:config:validate [files...]` | Checks configs the way an upload is checked (the final config against the schema, raster layers need `layer_width` and `layer_height`) |
| `app:config:merge <file>` | Gives the final config of a config: merged with its parents |
| `app:config:verify [files...]` | Checks that configs merged with their parents give the originals they were made from |
| `app:config:strip [files...]` | Removes what a parent already provides from configs (`--parent=NAME`) |
| `app:config:split` | Makes a generic config of the configs that share data, and strips the configs against it |

Options that most commands have:

| Option | Meaning |
|---|---|
| `--dir=DIR` | The config root (see above) |
| `--pattern=GLOB` | File name pattern of the configs to look at, anywhere below `--dir` (default `*.json`) |
| `--format=text\|json` | `text` for people (the default), `json` for programs (see below) |
| `--skip-validation` | (`split`, `strip`) do not check complete configs against the schema first |

More options, in short:

- `merge`: `--output=FILE` writes the result to a file.
- `verify`: `--against=REV` (a git revision, default `HEAD`), `--repo=DIR`, `--original-dir=DIR` (originals in a folder
  with the same paths below it).
- `strip`: `--parent=NAME`, `--apply` (replace the files, each one verified first), `--check` (exit 1 when a file could
  still be stripped), `--output-dir=DIR`.
- `split`: `--generic=NAME` (default `generic`), `--apply`, `--check`, `--force` (overwrite an existing generic
  config), `--output-dir=DIR`.
- `list`: `--check` (exit 1 when something is wrong).

Without `--apply` or `--output-dir`, `strip` and `split` only report. See `--help` of a command for everything.

## Exit codes

| Code | Meaning |
|---|---|
| 0 | It went well |
| 1 | It found a problem (`verify`, `validate`, `--check`) or could not do what was asked (a file or a parent is missing, nothing could be written) |
| 2 | It was used wrongly (options that exclude each other, an unknown `--format`, an invalid name) |

The exit code of the process is also in the JSON document (`exitCode`).

## Output for programs: `--format=json`

With `--format=json` a command prints **one JSON document on stdout, and nothing else**, also when it fails. Nothing is
printed on stderr by the command. The document is on one line.

```json
{
  "schema": 1,
  "command": "app:config:merge",
  "success": true,
  "exitCode": 0,
  "errors":   [ { "code": "parent_missing", "message": "...", "file": "NS/ns", "details": { "parent": "generic" } } ],
  "warnings": [ { "code": "split_warning", "message": "..." } ],
  "data": { }
}
```

- `schema` is the version of the document. It goes up when something is removed or changes its meaning. A field that is
  added is not a change: ignore fields you do not know.
- `success` is `exitCode === 0`.
- `errors` is what made the command stop (or, for a check, what it found). Every error has a `code`, a `message` for
  people, and often a `file` (the id of the config, or the file as it was given to `merge`) and `details`. **Use the
  code, not the message.**
- `warnings` do not stop the command. They have a `code`, a `message`, and sometimes `details`.
- `data` is what the command found out. It is `{}` when the command stopped before it knew anything.
- Config ids are the path of the config below the root without `.json`, for example `NS/Digitwin/2000/NS_DT_2000`.
  Paths are relative to the root (with `/`), except where it says absolute.

**Reading a config in the document.** A config has objects with keys that are numbers (`"layer_type": {"0": {...}}`).
A program that reads JSON into PHP arrays turns those into lists. In PHP use `json_decode($json)` (objects). Other
languages read it as an object as it is.

**Large documents.** `merge` puts the whole final config in the document (about 1 MB). Use `--output=FILE` to have it
written to a file and get only the path.

**Text mode.** The text for people is made from this same document (a command tells what it found to the document,
and one renderer writes the text), so everything that a person sees is in the JSON too. `merge` prints the config on
stdout and its messages on stderr, so that a pipe stays clean. The other commands print their messages on stdout.

### `app:config:list`

```json
{ "data": {
  "root": "/abs/path/to/root",
  "configs": [ { "id": "NS/ns", "path": "NS/ns.json", "kind": "stripped", "parent": "generic",
                 "chain": ["public", "generic"], "layers": 94, "problem": null } ],
  "parents": [ { "name": "generic", "id": "generic", "path": "generic.json", "parent": null, "layers": 96,
                 "children": ["NS/ns"], "problem": null } ],
  "duplicates": [ { "name": "generic", "paths": ["NS/generic.json", "generic.json"] } ],
  "skipped":    [ { "path": "B/broken.json", "reason": "not valid JSON: ..." } ],
  "summary": { "configs": 6, "parents": 1, "problems": 0, "duplicates": 0, "skipped": 0 } } }
```

- `kind` is `stripped` (it needs a parent) or `complete`. `chain` is the parents, nearest first (empty when there are
  none, or when the chain is broken: `problem` says why).
- `problem` is `null`, or an error without a `file`: `{code, message, details?}` (for example `parent_missing`).
- `duplicates` are names that two files have, where the name is the name of a parent or is used as one. A parent that
  has a duplicate name can not be found: the configs that use it have the problem `parent_ambiguous`.
- `skipped` are JSON files that are not valid JSON (they may be configs).
- `--check` ends with exit code 1 and the error `check_failed` when there are problems, duplicates or skipped files.

### `app:config:validate`

```json
{ "data": {
  "results": [ { "id": "NS/ns", "path": "NS/ns.json", "kind": "stripped", "valid": true,
                 "errors": [], "warnings": [] } ],
  "valid": 5, "invalid": 1 } }
```

- Every problem of a config is an entry in its `errors`: `{code, message, details?}`. `invalid_config` is a problem of
  the schema (the message names the property), `invalid_json` is a syntax error (the message has the line and
  column), and a parent that is missing has `parent_missing` and so on (see the codes below).
- When a config is not valid the command ends with the error `validation_failed` (details: `invalid`, `total`).
- A file that is not valid JSON is checked as well, and is reported as invalid.

### `app:config:merge`

```json
{ "data": { "file": "NS/ns",
            "parents": [ { "name": "generic", "path": "generic.json", "fingerprint": "649bf319..." } ],
            "config": { } } }
```

- `parents` are the parent files that the config was merged with, nearest parent first, and where they were found.
  `fingerprint` is a hash of the contents (xxh128): equal files have equal fingerprints.
- `config` is the final config. With `--output` there is `output` (the absolute path that was written) instead.
- The final config has no `metadata.parent` and its simulations are in `datamodel.simulation_settings`.

### `app:config:verify`

```json
{ "data": { "source": "git revision HEAD",
            "results": [ { "id": "NS/ns", "path": "NS/ns.json", "same": true, "differences": [] } ],
            "failed": 0 } }
```

Ends with the error `verify_failed` (details: `failed`, `total`) when a config does not give its original.

### `app:config:strip`

```json
{ "data": { "apply": false, "check": false, "outputDir": null, "written": 0,
            "results": [ { "id": "NS/ns", "path": "/abs/NS/ns.json", "status": "will_be_stripped",
                           "parent": "generic", "nowBytes": 1409000, "afterBytes": 861000,
                           "reasons": [], "warnings": [] } ] } }
```

- `status` is `will_be_stripped`, `already_stripped`, or `kept_as_is` (it can not be stripped without changing its
  meaning: `reasons` says why, and the file is left as it is).
- `written` is how many files were written (after `--apply` or with `--output-dir`).
- `--check` ends with the error `check_failed` (details: `configs`) when a file could still be stripped.

### `app:config:split`

```json
{ "data": {
  "apply": false, "check": false, "outputDir": null,
  "configs": [ { "id": "NS/ns", "path": "/abs/NS/ns.json", "layers": 94, "status": "will_be_stripped",
                 "nowBytes": 1409000, "afterBytes": 861000, "reasons": [] } ],
  "generic": { "name": "generic", "existed": true, "changed": false, "nowBytes": 552000, "afterBytes": 552000 },
  "total":   { "nowBytes": 5534000, "afterBytes": 5534000 },
  "layers":  { "generic": 96, "regionOnly": 241, "overrides": { "layer_tooltip": 89 }, "proposedNames": 0,
               "promoted": [], "demoted": [] },
  "sections": [ { "name": "restrictions", "participating": 6, "kind": "list", "values": 1, "genericValues": 0,
                  "distinctItems": 333, "genericItems": 7 } ],
  "removed": { "layer_information with a non-empty value": 26 },
  "written": { "generic": true, "genericPath": "generic.json", "configs": 6, "nothingToWrite": false } } }
```

- `nowBytes` and `afterBytes` are file sizes: the file now, and as it would be written (the same when nothing changes).
  `generic.nowBytes` is `null` when there is no generic config yet.
- `written` is only there after writing. `nothingToWrite` is true when what a split gives is on disk already: only
  files that would change are written.
- `--check` ends with the error `check_failed` (details: `genericChanged`, `configs`) when `--apply` would change
  something. When no config is found at all it ends with the error `no_configs`.

## Codes

### Errors

| Code | Meaning | `details` |
|---|---|---|
| `parent_missing` | A config names a parent that no file has the name of | `parent`, `neededBy`, `file` (the file that is needed, `<parent>.json`) |
| `parent_ambiguous` | Two files have the name of the parent | `parent`, `paths` |
| `parent_loop` | Parents are each other's parents | `chain` |
| `parent_too_deep` | More than 8 levels of parents | `maxDepth` |
| `parent_not_generic` | A file that is named as a parent has layers of its own (it is a config) | `parent`, `layer` |
| `parent_name_invalid` | `metadata.parent` is not a file name without extension (letters, digits, `_`, `-`) | `parent` |
| `parent_unreadable` | The file of a parent cannot be read as JSON | `parent`, `path` |
| `parent_required` | A config has layers with a `msp_config_generic_name` but no `metadata.parent` | |
| `invalid_json` | The file is not valid JSON (the message has the line and column) | |
| `invalid_config` | The config does not match the schema | `errors` (the messages) |
| `file_not_found` / `file_unreadable` | A file that was named does not exist, or cannot be read | |
| `file_not_json` | JSON files that are not valid JSON stop `split` and `strip` when they write | |
| `dir_not_found` | The config root does not exist | |
| `invalid_usage` | The options do not go together, or a name is invalid | |
| `validation_failed` | `validate` found configs that are not valid | `invalid`, `total` |
| `verify_failed` | `verify` found configs that do not give their original | `failed`, `total` |
| `check_failed` | `--check` found something (see the commands) | |
| `no_configs` | `split` found no config | |
| `generic_exists` | `split --apply` would overwrite the generic config: use `--force` | `path` |
| `generic_has_parent` | The generic config of `split` has a parent itself: choose another name with `--generic` | `generic` |
| `stripped_configs_unsafe` | `split` can not write stripped configs for the new generic config | `configs` |
| `write_failed` | Something could not be written or did not verify: nothing was changed | |
| `error` | Anything else: the message says what | |

### Warnings

| Code | Meaning |
|---|---|
| `merge_warning` | `merge` found something that it could not do exactly, and says what |
| `file_skipped` | A JSON file was left out because it is not valid JSON (`details.path`) |
| `no_configs` | Nothing was found to work on |
| `too_few_configs` | `split` has fewer than 2 configs: there is nothing to share |
| `generic_unusable` | The existing generic config can not be used, and is ignored (it can be overwritten with `--force`) |
| `config_not_strippable` | Configs that can not be stripped without changing their meaning, and stay as they are |
| `split_warning` | What `split` found while splitting (a SEL setting that is not covered, a reference that can not be resolved, copies of one config) |
| `warning` | Anything else |

New codes can be added. Programs should treat a code that they do not know as the general case (`error`, `warning`).

## How the editor uses it

This is how the tools are meant to be used by the config editor; it is refined with the editor team.

| The editor wants to | It runs |
|---|---|
| Show what is there | `list --dir ROOT --format=json` |
| Open a config | `merge FILE --dir ROOT --format=json` (the final config is `data.config`) |
| Check a config before saving | `validate FILE --dir ROOT --format=json` |
| Save a complete config | writes the file itself, then `validate` |
| Save a stripped config | writes the complete config to a folder, then `strip FILE --parent NAME --apply --dir ROOT` (the file is verified first), or `strip --output-dir` |
| Check a repository (CI) | `list --check`, `validate`, `verify --against REV` |

A program should check `schema` and refuse a document with a version it does not know, and should check the version of
the tool (`--version`) against the minimum that it was written for.
