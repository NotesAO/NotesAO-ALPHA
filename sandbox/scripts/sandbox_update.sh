#!/bin/bash
#
# sandbox_update.sh — self-sustaining quota-aware (1 Aug 2025 rev 4)
# ------------------------------------------------------------
# 0 Restore yesterday’s post-update dump (if any)
# 1 Pre-update backup
# 2 Sync exit status from FFLTest → sandbox
# 3 Maintain per-program mix
# 4 Sync sessions / attendance / absence / ledger
# 5 Post-update backup (+ copy to “yesterday”)
# 6 Purge old backups
# ------------------------------------------------------------
set -euo pipefail
IFS=$'\n\t'

DB_HOST="localhost"
SANDBOX_DB_USER="clinicnotepro_sandbox_app"
SANDBOX_DB_PASS="PF-m[T-+pF%g"
SANDBOX_DB_NAME="clinicnotepro_sandbox"
FFLTEST_DB_NAME="clinicnotepro_ffltest"

BACKUP_DIR="/home/notesao/sandbox/backups"
mkdir -p "$BACKUP_DIR"
RETENTION_DAYS=7

# ───── 0) restore yesterday’s dump ─────
YEST=$(date -d "yesterday" +%F)
YEST_FILE="$BACKUP_DIR/sandbox_backup_post_update_${YEST}.sql.gz"
if [[ -r "$YEST_FILE" ]]; then
  echo "☑  Restoring sandbox from $YEST_FILE"
  gunzip -c "$YEST_FILE" \
  | mysql -h "$DB_HOST" -u "$SANDBOX_DB_USER" -p"$SANDBOX_DB_PASS" "$SANDBOX_DB_NAME"
else
  echo "⚠  No post-update backup for $YEST_FILE (skipping restore)"
fi

# ───── 1) pre-update backup ─────
PRE="$BACKUP_DIR/sandbox_backup_pre_update_$(date +%F).sql.gz"
echo "☑  Creating pre-update backup → $PRE"
mysqldump --single-transaction --quick --skip-triggers \
          -h "$DB_HOST" -u "$SANDBOX_DB_USER" -p"$SANDBOX_DB_PASS" \
          "$SANDBOX_DB_NAME" client attendance_record absence ledger \
| gzip > "$PRE"

# ───── 2-4) main SQL work ─────
mysql -h "$DB_HOST" -u "$SANDBOX_DB_USER" -p"$SANDBOX_DB_PASS" "$SANDBOX_DB_NAME" <<'SQL'

/* ---------- helper IDs ---------- */
SET @active_id   := (SELECT id FROM exit_reason WHERE reason RLIKE 'not.?exited' LIMIT 1);
SET @completed_id:= (SELECT id FROM exit_reason WHERE reason RLIKE 'complete'  LIMIT 1);
SET @violated_id := (SELECT id FROM exit_reason WHERE reason RLIKE 'violate'   LIMIT 1);
SET @active_id    := IFNULL(@active_id,1);
SET @completed_id := IFNULL(@completed_id,3);
SET @violated_id  := IFNULL(@violated_id ,4);

/* ---------- 2) Sync exit status ---------- */
UPDATE client s
JOIN clinicnotepro_ffltest.client f ON s.real_client_id = f.id
SET  s.exit_reason_id = f.exit_reason_id,
     s.exit_date      = f.exit_date
WHERE s.exit_reason_id <> f.exit_reason_id
   OR s.exit_reason_id IS NULL XOR f.exit_reason_id IS NULL
   OR s.exit_date      <> f.exit_date
   OR s.exit_date      IS NULL XOR f.exit_date IS NULL;

/* ---------- 3) quota control (never delete rows) ---------- */
CREATE TEMPORARY TABLE mix_goal(
 program_id INT PRIMARY KEY,
 want_active INT, want_completed INT, want_other INT);
INSERT INTO mix_goal VALUES
 (1,25,10,5),(2,51,20,10),(3,25,10,5),(4,25,10,5);

/* 3-C recycle surplus completed / violated */
CREATE TEMPORARY TABLE recycle_ids AS
SELECT *
FROM (
  SELECT c.id,c.program_id,c.exit_reason_id,
         ROW_NUMBER() OVER (PARTITION BY c.program_id ORDER BY c.exit_date ASC) rn
  FROM client c
  WHERE c.exit_reason_id IN (@completed_id,@violated_id)
) ranked
JOIN mix_goal g USING(program_id)
WHERE (exit_reason_id=@completed_id AND rn>g.want_completed)
   OR (exit_reason_id<>@completed_id AND rn>g.want_other);

/* 3-E fetch missing actives from FFLTest */
CREATE TEMPORARY TABLE new_real_ids AS
SELECT f.*,
       f.orientation_date AS real_orient,
       ROW_NUMBER() OVER (PARTITION BY f.program_id ORDER BY f.id DESC) rn
FROM clinicnotepro_ffltest.client f
JOIN (
  SELECT program_id,
         GREATEST(want_active-
                  (SELECT COUNT(*) FROM client c
                   WHERE c.program_id=g.program_id
                     AND c.exit_reason_id=@active_id),0) need
  FROM mix_goal g) need USING(program_id)
WHERE f.exit_reason_id IS NULL
  AND need.need>0
  AND f.id NOT IN (SELECT real_client_id FROM client);

/* wipe prior activity for rows we’re about to recycle */
DELETE ar FROM attendance_record ar JOIN recycle_ids r ON r.id = ar.client_id;
DELETE ab FROM absence          ab JOIN recycle_ids r ON r.id = ab.client_id;
DELETE lg FROM ledger           lg JOIN recycle_ids r ON r.id = lg.client_id;

/* 3-F overwrite recycled slots */
UPDATE client c
JOIN recycle_ids r ON r.id=c.id
JOIN new_real_ids n ON n.program_id=r.program_id AND n.rn=r.rn
SET c.real_client_id    = n.id,
    c.exit_reason_id    = @active_id,
    c.exit_date         = NULL,
    c.orientation_date  = n.real_orient,
    c.gender_id         = n.gender_id,
    c.referral_type_id  = n.referral_type_id,
    c.required_sessions = n.required_sessions,
    c.fee               = n.fee,
    c.therapy_group_id  = n.therapy_group_id;

/* 3-G insert extra actives if still short */
INSERT INTO client (real_client_id,exit_reason_id,first_name,last_name,
                    program_id,gender_id,referral_type_id,
                    required_sessions,fee,therapy_group_id,
                    orientation_date,date_of_birth,email,phone_number)
SELECT n.id,@active_id,
       CONCAT('Client_',LPAD(n.id,6,'0')),
       CONCAT('Sandbox_',LPAD(n.id,6,'0')),
       n.program_id,n.gender_id,n.referral_type_id,
       n.required_sessions,n.fee,n.therapy_group_id,
       n.real_orient,'2000-01-01',NULL,NULL
FROM new_real_ids n
LEFT JOIN recycle_ids r USING(program_id,rn)
WHERE r.id IS NULL;

/* 3-H re-activate newest exits if quota still unmet, and purge old activity */
UPDATE client c
JOIN (
  SELECT g.program_id,
         GREATEST(g.want_active-
                  SUM(c.exit_reason_id=@active_id),0) still_need
  FROM mix_goal g
  LEFT JOIN client c ON c.program_id=g.program_id
  GROUP BY g.program_id
) q USING(program_id)
SET c.exit_reason_id=@active_id,
    c.exit_date     =NULL,
    c.orientation_date=COALESCE(c.orientation_date,CURDATE())
WHERE c.exit_reason_id IS NOT NULL
  AND q.still_need>0
ORDER BY c.exit_date DESC
LIMIT 100;

DELETE ar FROM attendance_record ar
JOIN client c ON c.id=ar.client_id
WHERE c.exit_reason_id=@active_id AND c.orientation_date=CURDATE();
DELETE ab FROM absence ab
JOIN client c ON c.id=ab.client_id
WHERE c.exit_reason_id=@active_id AND c.orientation_date=CURDATE();
DELETE lg FROM ledger lg
JOIN client c ON c.id=lg.client_id
WHERE c.exit_reason_id=@active_id AND c.orientation_date=CURDATE();

/* ---------- 4) session / attendance / absence / ledger ---------- */

INSERT INTO therapy_session (id,therapy_group_id,date,duration_minutes,
                             curriculum_id,facilitator_id,note)
SELECT ts.id,ts.therapy_group_id,ts.date,ts.duration_minutes,
       ts.curriculum_id,ts.facilitator_id,ts.note
FROM clinicnotepro_ffltest.therapy_session ts
WHERE ts.id NOT IN (SELECT id FROM therapy_session);

INSERT INTO attendance_record (client_id,therapy_session_id,note)
SELECT s.id,ts.id,ar.note
FROM clinicnotepro_ffltest.attendance_record ar
JOIN client          s  ON s.real_client_id = ar.client_id
JOIN therapy_session ts ON ts.id           = ar.therapy_session_id
LEFT JOIN attendance_record chk
           ON chk.client_id=s.id AND chk.therapy_session_id=ts.id
WHERE chk.client_id IS NULL
  AND NOT EXISTS (
        SELECT 1 FROM attendance_record w
        JOIN therapy_session t2 ON t2.id=w.therapy_session_id
        WHERE w.client_id=s.id
          AND YEARWEEK(t2.date,1)=YEARWEEK(ts.date,1)
      );

INSERT INTO absence (client_id,date,excused,note)
SELECT s.id,a.date,a.excused,a.note
FROM clinicnotepro_ffltest.absence a
JOIN client s ON s.real_client_id=a.client_id
LEFT JOIN absence chk ON chk.client_id=s.id AND chk.date=a.date
WHERE chk.client_id IS NULL;

INSERT INTO ledger (client_id,amount,create_date,note)
SELECT s.id,l.amount,l.create_date,l.note
FROM clinicnotepro_ffltest.ledger l
JOIN client s ON s.real_client_id=l.client_id
LEFT JOIN ledger chk ON chk.client_id=s.id AND chk.create_date=l.create_date
WHERE chk.client_id IS NULL;

/* ---------- 4-E  trim any excess attendance (newest-first) ---------- */
DELETE ar
FROM   attendance_record ar
JOIN (
        SELECT ar2.client_id,
               ar2.therapy_session_id,
               ROW_NUMBER() OVER (
                   PARTITION BY ar2.client_id
                   ORDER BY      ts2.date DESC          -- newest = 1
               ) AS rn,
               c.required_sessions
        FROM   attendance_record ar2
        JOIN   therapy_session  ts2 ON ts2.id = ar2.therapy_session_id
        JOIN   client           c   ON c.id  = ar2.client_id
) AS ranked
      ON  ranked.client_id          = ar.client_id
     AND ranked.therapy_session_id  = ar.therapy_session_id
WHERE ranked.rn > ranked.required_sessions;
-- -------------------------------------------------------------------- 
/* ---------- 4-F  auto-complete clients who met their quota ---------- */
UPDATE client c
JOIN (
        SELECT  ar.client_id,
                MAX(ts.date)      AS last_session,
                COUNT(*)          AS att_cnt
        FROM   attendance_record ar
        JOIN   therapy_session  ts ON ts.id = ar.therapy_session_id
        GROUP  BY ar.client_id
) a  ON a.client_id = c.id
SET c.exit_reason_id = @completed_id,
    c.exit_date      = a.last_session
WHERE c.exit_reason_id = @active_id      -- only currently active
  AND a.att_cnt       >= c.required_sessions;
-- -------------------------------------------------------------------- 


SQL

echo "☑  Sandbox client updates completed"

# ───── 5) post-update backup ─────
POST="$BACKUP_DIR/sandbox_backup_post_update_$(date +%F).sql.gz"
echo "☑  Creating post-update backup → $POST"
mysqldump --single-transaction --quick --skip-triggers \
          -h "$DB_HOST" -u "$SANDBOX_DB_USER" -p"$SANDBOX_DB_PASS" \
          "$SANDBOX_DB_NAME" client attendance_record absence ledger \
| gzip > "$POST"

cp "$POST" "$BACKUP_DIR/sandbox_backup_post_update_$(date -d yesterday +%F).sql.gz"

# ───── 6) purge old backups ─────
echo "🧹  Removing backups older than $RETENTION_DAYS days"
find "$BACKUP_DIR" -type f -name 'sandbox_backup_*.sql.gz' -mtime "+$RETENTION_DAYS" -delete

echo "✅  Finished sandbox_update.sh"
