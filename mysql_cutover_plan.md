# MySQL High-Volume Cutover Plan

This plan keeps the current stack unchanged: PHP, JavaScript, CSS, and MySQL.

Use this together with [mysql_high_volume_partitioning.sql](c:/xampp/htdocs/factory_attendance_system/mysql_high_volume_partitioning.sql).

## Goal

Move hot punch tables toward a high-volume operating model with:

- fast live writes on hot partitions
- archive tables for cold data
- retained application compatibility through the existing PHP API
- no framework or language migration

## Preconditions

1. Confirm your MySQL edition and version on staging and production.
2. Confirm table sizes and daily row growth for:
   - `attendance_punches`
   - `vehicle_punches`
   - `attendance_daily_summary`
3. Take a verified logical backup and, if available, a physical snapshot.
4. Test the full cutover on staging with production-like data volume.
5. Schedule a maintenance window if table renames or bulk backfills are required.

## Phase 1: Prepare

1. Apply the latest application code so rollup summaries and new indexes are already in use.
2. Review [mysql_high_volume_partitioning.sql](c:/xampp/htdocs/factory_attendance_system/mysql_high_volume_partitioning.sql).
3. Create the archive tables first.
4. Create the partition-ready replacement tables on staging.
5. Measure insert speed, range query speed, and export speed before and after.

## Phase 2: Backfill on Staging

1. Copy data into the partition-ready tables in batches.
2. Validate row counts per day and per employee between source and target tables.
3. Run the application against the staging copy and verify:
   - punch in/out
   - vehicle sessions
   - attendance records
   - attendance export
   - vehicle usage export

## Phase 3: Production Cutover

1. Put the application in maintenance mode or block punch writes briefly.
2. Apply the partition-ready table creation if not already present.
3. Backfill the latest delta rows from current hot tables.
4. Verify final row counts and spot-check recent data.
5. Rename tables during the cutover window.

Example sequence:

```sql
RENAME TABLE attendance_punches TO attendance_punches_legacy,
             attendance_punches_partitioned TO attendance_punches;

RENAME TABLE vehicle_punches TO vehicle_punches_legacy,
             vehicle_punches_partitioned TO vehicle_punches;
```

6. Release maintenance mode.
7. Run a smoke test from the browser:
   - user login
   - punch with GPS
   - attendance dashboard load
   - vehicle usage load

## Phase 4: Post-Cutover Validation

1. Compare row counts between legacy and new tables for a defined time window.
2. Watch MySQL metrics:
   - query latency
   - row lock waits
   - disk growth
   - slow query log
3. Confirm that `attendance_daily_summary` continues updating after new punches.
4. Leave the `_legacy` tables in place until rollback risk is low.

## Phase 5: Archival Operations

1. Define a hot-data window, for example 30 to 90 days.
2. Move older rows into archive tables using the procedures from [mysql_high_volume_partitioning.sql](c:/xampp/htdocs/factory_attendance_system/mysql_high_volume_partitioning.sql).
3. Run archive operations during off-peak hours.
4. Keep report endpoints on hot tables and summaries unless historical archive access is required.

## Monthly Partition Maintenance

1. Add next month’s partition before the current month ends.
2. Keep a `pmax` partition available at all times.
3. Monitor partition count and storage per partition.

Example:

```sql
CALL add_next_month_attendance_partition('attendance_punches_partitioned', 'p2026_09', '2026-10-01 00:00:00');
CALL add_next_month_attendance_partition('vehicle_punches_partitioned', 'p2026_09', '2026-10-01 00:00:00');
```

## Rollback Plan

1. Re-enable maintenance mode.
2. Rename the live tables back to the legacy versions.
3. Restore the last delta writes if needed.
4. Re-run smoke tests.

Example:

```sql
RENAME TABLE attendance_punches TO attendance_punches_partitioned_failed,
             attendance_punches_legacy TO attendance_punches;

RENAME TABLE vehicle_punches TO vehicle_punches_partitioned_failed,
             vehicle_punches_legacy TO vehicle_punches;
```

## Practical Limits

This project is now faster and more reliable in the current stack, but truly extreme sustained write rates still need operational discipline:

- separate database host, not a local XAMPP machine
- enough disk throughput and RAM for active partitions
- slow query monitoring
- backup and archive policies
- periodic partition maintenance

The application code is prepared to benefit from that setup, but infrastructure still matters.