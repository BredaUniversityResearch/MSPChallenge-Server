# Config tests

Tests for the session config tooling in `src/Domain/Config` and `src/Command/Config*`: splitting complete configs into
a generic config (the parent) and small region configs, merging them back, stripping, verifying, parents and
chains of parents, the loader and validator used by the Server Manager, uploading a config together with the parents
it needs (in as many steps as it takes), and the commands around them.

```bash
vendor/bin/phpunit tests/ServerManager/Config                                  # all of them
vendor/bin/phpunit tests/ServerManager/Config --filter ConfigLoaderTest        # one class
vendor/bin/phpunit tests/ServerManager/Config --filter testItFollowsAChainOfParents   # one test
vendor/bin/phpunit tests/ServerManager/Config --verbose                        # also says why tests were skipped
```

357 test cases in 20 classes (data providers count as one case per data set). A full run takes one to a few minutes:
most tests work on the six real configs.

## The ideas behind the tests

- **The six original configs are the test data.** They come from git revision `d6e5d75` of this repository
  (`ServerManager/configfiles`), and are downloaded once, checked against pinned SHA-256 sums and cached in
  `var/test-fixtures/`. Without network access PHPUnit reports an **error** (never a skip), so a run can not pass by
  testing nothing. Use `MSP_CONFIG_FIXTURES_DIR=<directory with the six configs>` to use a local copy.
- **Everything has to be lossless.** Splitting, stripping and merging are verified by merging back and comparing with
  the original (`ConfigComparator`), on synthetic configs and on the real ones.
- **Inputs are never modified**, and the result is deterministic.
- **Output is checked independent of the terminal.** `ConfigCommandTestCase` fixes `COLUMNS` at 120 and tests read
  command output with `self::text()` (whitespace collapsed), so wrapping in `[OK]`/`[ERROR]` blocks does not matter.

## Test classes

| Class | Cases | What it covers |
|---|---:|---|
| `GenericNameRegistryTest` | 12 | How layers get a generic name: the PascalCase of `layer_short`, the fixed name of play area layers, fallbacks, layers of different configs with the same short share a name, a geotype suffix when the geotype differs, two layers of one config never share a name, an existing layer name map decides and conflicts are rejected, `\|type` suffixes of references, a sorted map that round-trips. |
| `ConfigSplitterTest` | 24 | Splitting complete configs into a generic config and region configs: shared layers become generic and only differences stay in the region, layers of one config only stay in that config (without a generic name, referred to by their layer name), `layer_names` travel with the generic config, region configs always declare `simulation_settings`, CEL/SEL/MEL in `simulation_settings`, restrictions and dependencies, SEL lists with layer references, fields removed by the design document (raster layers keep `layer_width`/`layer_height`), warnings, layer order, determinism, real configs split losslessly and smaller. |
| `RegionConfigMergerTest` | 35 | Merging a region config with its generic config into the final config: layer names, layers without a generic counterpart, skipped items for layers the region does not have, simulation settings and layer references, explicit nulls, dependencies, old-style top-level CEL/SEL/MEL that override `simulation_settings`, complete configs merged with a generic config underneath, merging two generic configs (the pool of a parent and a child), format detection, real configs merge back to the originals. |
| `RegionConfigStripperTest` | 27 | Stripping a config against a generic config: lossless and idempotent, only differences stay, layers without a generic counterpart and aliases for name clashes, what can **not** be stripped without changing the meaning (it is refused), inheriting settings and dependencies that the config lacks, old-style keys, the parent name written to `metadata.parent`, raster dimensions, real configs lossless, stable and smaller when the generic config grows. |
| `ConfigFileStripperTest` | 10 | Stripping at file level: plans (changed, unchanged, refused), a verified write (temporary file, read back, merged back) that never replaces a file with a wrong version, staged writes that are committed afterwards, the final config a file has to keep giving back. |
| `ConfigComparatorTest` | 12 | The comparison used everywhere: key order and number types are ignored, additive lists are compared as sets, other lists keep their order, missing and unexpected items, restrictions, simulations, layer fields and metadata, a limit on the number of reported differences. |
| `ConfigDirectoryTest` | 25 | The config folder: finding configs (one folder deep, no `.region.json`, patterns), reading (byte order mark, JSON object), resolving file arguments, generic config files in the folder root and which parent names are valid, JSON encoding like the originals (two spaces, newline, floats, size), safe replacing and staged writes, showing paths (also on another drive). |
| `ConfigParentsTest` | 23 | `metadata.parent`: the pool of a config, of a parent and of a chain (the child adds and changes layers by name, siblings do not see each other), layer names along the chain, errors for a missing parent, a missing parent higher up, loops, too many levels, a parent that is not a generic config, invalid names and an unreadable file, the real configs through a chain of two generic configs, no parent in the final config. |
| `ConfigLoaderTest` | 35 | The loader of the Server Manager: merging stripped, complete and chained configs, `normalize` (a final config is not merged again), a changed parent is read again, error messages, the merged JSON has the shape of the schema, both shapes readable, and uploads: stored as the complete final config, an unknown parent is refused with the file that is needed, a download that is edited and uploaded again keeps the edit, JSON syntax errors with line numbers, schema errors, uploads of several files (the uploaded parents are used before the ones of the server, and are never stored), and the cache of merged configs: served the second time, shared by loaders with the same pool, a changed config or a changed parent is merged again (also when the parent file keeps its size and its time), a parent that is gone is an error, nothing is cached on failure, a cache that does not work does no harm. |
| `UploadInspectorTest` | 15 | Looking at the files of an upload: a config alone, with its parents, with parents that the server has, a chain that is partly uploaded, which file is missing and which file needs it, only generic configs (the configuration itself is missing), two configurations are refused, parents that are not needed, loops, a chain that is too long, invalid JSON with its line, a stripped config without a parent, parents are found by the name of their file. |
| `PendingConfigUploadsTest` | 15 | The files of uploads that are not complete: kept in upload order, a file with the same name replaces the old one, names can not point anywhere else, a limit on the number of files, discarding, tokens of the right shape only, uploads that are too old are gone and purged. |
| `ConfigUploadsTest` | 16 | Uploading in steps: a config without parents is processed at once, a missing parent is asked for and the files are kept, the missing parent completes the upload and nothing is kept (or stored on the server), forgetting the configuration, parents of the server, an invalid file rejects only that submission, a second configuration is rejected, cancelling, replacing a file, a complete upload that is no valid config, an upload that became complete, expired uploads, foreign tokens. |
| `JsonSyntaxTest` | 22 | The JSON syntax error locator: valid forms, 16 kinds of errors with line and column and a message, agreement with `json_decode`, the real configs have no errors, short one-line messages. |
| `SessionConfigValidatorTest` | 14 | The schema and the rules it can not express: the final config of every real config is valid (also old-style ones once normalized), null SEL/MEL are fine and CEL is not, `simulation_settings` is required, optional fields of the design document, a raster layer needs `layer_width` and `layer_height`, other required properties and types, `validate()` throws with all errors, the limit on the number of errors, the short summary. |
| `ConfigSplitCommandTest` | 23 | `app:config:split`: validation against the schema, a dry run by default, `--apply`, `--output-dir`, `--force`, `--check`, `--generic=NAME`, splitting stripped configs again is a fixed point, a layer that a second config uses becomes generic and moves back when the config is gone, a parent that has a parent itself is not replaced, files that did not change are not rewritten, paths on another drive, report and error messages. |
| `ConfigStripCommandTest` | 20 | `app:config:strip`: report only by default, `--apply` with verified writes, `--check`, `--output-dir` (the parents go along), `--parent`, stripping against a parent that has a parent, files outside the config folder, re-stripping when the generic config grows, configs that can not be stripped are kept, an edited merged download is stripped losslessly, validation, missing parents and invalid files. |
| `ConfigMergeCommandTest` | 11 | `app:config:merge`: prints the final config of a stripped file, follows a chain of parents, a complete config needs no parent, the output is raw JSON, old-style keys are applied, `--output`, and errors for a missing parent, a stripped config that does not name its parent, a parent that is not a generic config and a missing file. |
| `ConfigVerifyCommandTest` | 9 | `app:config:verify`: configs merged with their parents compared with the originals from a directory or from a git revision, changed configs are reported with their differences, missing originals and parents, a chain of parents, files outside the repository. |
| `ConfigFixturesTest` | 6 | The test data itself: the six originals, complete (not stripped), a directory with the same files is accepted, a file that is not the original is rejected, a missing file is reported, the download is cached as a zip with the same bytes. |
| `ShippedConfigsTest` | 3 | The configs shipped in `ServerManager/configfiles`, in whatever state they are: still the original configs when merged with their parents, stripped ones can not be stripped further, and the generic configs know the layer names. The two checks on stripped configs skip (not fail) while the shipped configs are still complete. |

## Helpers (not tests)

| Class | Purpose |
|---|---|
| `ConfigTestCase` | Base class (a `KernelTestCase`): the real configs, `realSplit()`, a shared merger, normalizer and comparator, and assertions that compare big values by size and MD5, so a failure is reported at once instead of after minutes of diffing. |
| `ConfigCommandTestCase` | Base class for tests on a temporary config folder and for the commands: temporary directories, the original configs (`writeOriginals()`), `writeGeneric()` and `writeChildGeneric()` for the parents on the server, `strippedCopy()`, `dropAGenericPortLayer()` (a config that can not be stripped), snapshots of the folder, a fixed terminal width, `text()`. Also the files of an upload as JSON: `genericJson()`, `strippedJson()`, `emptyChildGenericJson()`, `smallConfigJson()`. |
| `ConfigFactory` | Small synthetic configs for the unit tests. |
| `ArrayCachePool`, `ArrayCacheItem` | A cache pool in memory that counts its saves, so a test can see whether a cache was used (several loaders can share one, like processes share the cache of the application), and that can be made to fail. |
| `ConfigFixtures` | Downloads, verifies and caches the six original configs. |

## What these tests do not cover

- **The Server Manager screens.** The upload form (several files), its controller (`GameConfigVersionController`),
  the modal (`gameconfigversion_form.html.twig`, `modal-gameconfig_controller.js`) and the cancel route have no
  automated test here. The logic behind them is covered: `ConfigUploads`, `UploadInspector`,
  `PendingConfigUploads` and `ConfigLoader`.
- **Creating a session and restoring a save.** The code that writes the merged running config and validates saves
  (`GameListCreationMessageHandler`, `CommonSessionHandler`, `GameSaveZipFileValidator`, the entity listeners and
  `api/v1/Game.php`) uses `ConfigLoader`, which is tested, but the wiring itself is only checked by the tests of the
  rest of the application.
- **The Unity client and the config editor**, and a real run of `app:config:split --apply` on the released configs.

