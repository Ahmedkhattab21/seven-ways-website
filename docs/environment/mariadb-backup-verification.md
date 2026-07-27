# MariaDB Backup Verification

## Result

- Source: `D:\xxamp\mysql\data`
- Backup: `C:\seven-ways-backups\mysql-data-20260727-164444\data`
- Method: full copy; the source was not moved, repaired, or modified.
- Source files: 1,187
- Backup files: 1,187
- Source bytes: 221,230,018
- Backup bytes: 221,230,018
- Storage: separate `C:` drive from the source on `D:`.
- Result: file count and total size match exactly.

## Checksums

| File | SHA-256 |
| --- | --- |
| `ibdata1` | `C930135C8CF21D195D3206097FD3CCC48089025976F0836ACC09A3C4FE590E43` |
| `ib_logfile0` | `315B3F08852F066A2B6DF8FB8C21D66BFD0A19FAF859F3E3355FD1031444E438` |
| `ib_logfile1` | `B5FAD06F8EB5FE69D5496A42E0629918AD8ABB5798B79F1D37E76D8282C0871A` |
| `aria_log_control` | `C05CD1CB1DEA8581A264DE54346E0236870E50F82AAA59EB8D34AF5087B1EF06` |
| `mysql_error.log` | `9AF3EDF455BB704FBCE6DD2E4D0109BECC2BDDFAC4F7D7F4CBB4C9E63143CF94` |

Configuration copies are stored under the backup `configuration` directory. No file failed to copy.

