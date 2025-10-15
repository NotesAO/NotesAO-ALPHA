for e in entries:
    meta = e["meta"]
    dob  = meta.get("1t1xj-value")                 # DOB field key
    if not dob:
        continue
    fn = meta["u1ptl34-value"]["first"].lower()    # first name field key
    ln = meta["u1ptl34-value"]["last"].lower()     # last  name field key

    for cid, cfn, cln in by_dob.get(dob, []):
        score = (difflib.SequenceMatcher(None, fn, cfn).ratio() +
                 difflib.SequenceMatcher(None, ln, cln).ratio()) / 2
        if score >= THRESH:
            matches.append((cid, fn.title(), ln.title(), dob, f"{score:.2f}"))
            break

# ---- output ------------------------------------------------------------------
print(f"\nMatched {len(matches)} API entries (DOB exact, name ≥{THRESH*100:.0f}%):\n")
for cid, fn, ln, dob, sc in sorted(matches, key=lambda x: (ln, fn)):
    print(f"{cid:5}  {fn} {ln}  ({dob})  score={sc}")
PY

#1747163661
python3 ~/ffl_match.py
#1747163758
which python3
#1747163758
python3 -V
#1747163758
which pip3
#1747163758
pip3 --version
#1747163792
ython3 -m pip install --user --upgrade requests pymysql
#1747163908
python3 -m pip install --user --upgrade requests pymysql
#1747163939
python3 -m ensurepip --upgrade --user   # installs pip into your user site-packages
#1747163941
python3 -m pip install --user --upgrade pip  # upgrade to the latest pip
#1747163943
python3 -m pip install --user requests pymysql
#1747163948
python3 - <<'PY'
import sys, importlib
for pkg in ("requests", "pymysql"):
    importlib.import_module(pkg)
print(sys.executable, "imports are OK")
PY

#1747164207
nano ~/ffl_match.py
#1747164303
python3 ~/ffl_match.py
#1747164374
python3 - <<'PY'
import requests, json, pprint
resp = requests.get(
    "https://freeforlifegroup.com/wp-json/frm/v2/forms/15/entries?per_page=2&meta=1",
    auth=("F3IZ-ONWL-6QLT-5MWH", "x"), timeout=15
).json()
pprint.pp(resp)
PY

#1747164585
nano ~/ffl_match.py
#1747164664
python3 ~/ffl_match.py
#1747249767
# compare ffltest → ctc and write a report
#1747249767
diff -r --brief      --exclude='*.log' --exclude='error_log' --exclude='*.log.*'      --exclude='logs'  --exclude='*/logs/*'      --exclude='*.tmp' --exclude='*.cache'      --exclude='*.bak' --exclude='*.old'      /home/notesao/ffltest /home/notesao/ctc   | tee ~/diff_ffltest_vs_ctc.txt
#1747251987
diff -r --brief      --exclude='*.log' --exclude='error_log' --exclude='*.log.*'      --exclude='logs'  --exclude='*/logs/*'      --exclude='*.tmp' --exclude='*.cache'      --exclude='*.bak' --exclude='*.old'      /home/notesao/ffltest /home/notesao/dwag   | tee ~/diff_ffltest_vs_dwag.txt
#1747320066
diff -r --brief      --exclude='*.log' --exclude='error_log' --exclude='*.log.*'      --exclude='logs'  --exclude='*/logs/*'      --exclude='*.tmp' --exclude='*.cache'      --exclude='*.bak' --exclude='*.old'      /home/notesao/ffltest /home/notesao/safatherhood   | tee ~/diff_ffltest_vs_safatherhood.txt
#1747321681
diff -r --brief      --exclude='*.log' --exclude='error_log' --exclude='*.log.*'      --exclude='logs'  --exclude='*/logs/*'      --exclude='*.tmp' --exclude='*.cache'      --exclude='*.bak' --exclude='*.old'      /home/notesao/ffltest /home/notesao/transform   | tee ~/diff_ffltest_vs_transform.txt
#1747340440
# from your home directory (notesao@host ~)$
#1747340440
grep -RIn --color     --exclude-dir="{logs,GeneratedDocuments}"     --exclude="*.{log,tmp,cache,bak,old,zip,pdf,png,jpg,jpeg,gif,svg,ico}"     -e 'facilitator' /home/notesao/ctc/public_html/
#1747679629
diff -r --brief --new-file --exclude='.git' --exclude='vendor'      /home/notesao/sandbox/ /home/notesao/ffltest/ | sort
#1747679793
diff -r --brief --new-file --exclude='*.log' --include='*.php'      /home/notesao/sandbox/ /home/notesao/ffltest/ | sort
#1747680053
diff -r --brief --new-file --exclude='.git' --exclude='vendor'      /home/notesao/sandbox/ /home/notesao/ffltest/ | sort      > /home/notesao/ffltestvsandbox.txt 2>&1
#1747680146
# run from *any* directory on the server
#1747680146
tmp="$(mktemp)" && diff -r --brief --new-file      --exclude='.git' --exclude='vendor'      /home/notesao/sandbox /home/notesao/ffltest   | awk '/ differ$/{print $2}'   | grep -Ev '(/logs/|/error_log$|\.((docx)|(pdf)|(png)|(zip)|(ico))$)'   | sort > "$tmp" && while read -r f; do     twin="${f/\/sandbox\//\/ffltest\/}"     [ -f "$f" ] && [ -f "$twin" ] && diff -u "$f" "$twin"; done < "$tmp" > /home/notesao/ffltest_vs_sandbox_detailed.patch && rm "$tmp"
#1747680147
echo "Detailed diff saved to /home/notesao/ffltest_vs_sandbox_detailed.patch"
#1747680553
# Make yourself a scratch directory
#1747680553
mkdir -p ~/patch_work
#1747680553
# Pull only the files we care about into a smaller diff
#1747680553
files='
 public_html/helpers.php
 public_html/home.php
 public_html/navbar.php
 public_html/auth.php
 public_html/client-update.php
 public_html/client-image-upload.php
 sql_functions.php
'
#1747680553
cd ~
#1747680553
diff -uNr --exclude='.git' --exclude='vendor'      $(echo "$files" | sed "s_^_sandbox/_")       $(echo "$files" | sed "s_^_ffltest/_")       > ~/patch_work/core_deltas.patch
#1747680576
rsync -a ~/sandbox/ ~/sandbox_merge_test/
#1747680577
patch -p2 -d ~/sandbox_merge_test/ < ~/patch_work/core_deltas.patch
#1747680655
# --------------------------------------------
#1747680655
#  build a compact patch of the seven key files
#1747680655
#  -------------------------------------------
#1747680655
mkdir -p ~/patch_work                    # no error if it already exists
#1747680655
files='
 public_html/helpers.php
 public_html/home.php
 public_html/navbar.php
 public_html/auth.php
 public_html/client-update.php
 public_html/client-image-upload.php
 sql_functions.php
'
#1747680655
# create the unified diff
#1747680655
diff -uNr --new-file --exclude='.git' --exclude='vendor'   $(echo "$files" | sed "s_^_sandbox/_")    $(echo "$files" | sed "s_^_ffltest/_")    > ~/patch_work/core_deltas.patch 2>/dev/null    # silence “permission denied” lines
#1747680655
echo
#1747680655
echo "✓ core_deltas.patch written to  ~/patch_work  ($(wc -l < ~/patch_work/core_deltas.patch)  lines)"
#1747680655
echo "  view with:   less -R ~/patch_work/core_deltas.patch"
#1747680739
cd /home/notesao           # <-- important!
#1747680739
files='
 public_html/helpers.php
 public_html/home.php
 public_html/navbar.php
 public_html/auth.php
 public_html/client-update.php
 public_html/client-image-upload.php
 sql_functions.php
'
#1747680739
diff -uNr --new-file --exclude='.git' --exclude='vendor'   $(echo "$files" | sed 's_^_sandbox/_')    $(echo "$files" | sed 's_^_ffltest/_')    > ~/patch_work/core_deltas.patch 2>/dev/null
#1747680739
echo "✓ $(wc -l < ~/patch_work/core_deltas.patch) lines written to  ~/patch_work/core_deltas.patch"
#1747680747
files='
 public_html/helpers.php
 public_html/home.php
 public_html/navbar.php
 public_html/auth.php
 public_html/client-update.php
 public_html/client-image-upload.php
 sql_functions.php
'
#1747680748
diff -uNr --new-file --exclude='.git' --exclude='vendor'   $(echo "$files" | sed 's_^_/home/notesao/sandbox/_')    $(echo "$files" | sed 's_^_/home/notesao/ffltest/_')    > ~/patch_work/core_deltas.patch 2>/dev/null
#1747680748
echo "✓ $(wc -l < ~/patch_work/core_deltas.patch) lines written to  ~/patch_work/core_deltas.patch"
#1747680751
less -R ~/patch_work/core_deltas.patch
#1747680855
# 0) adjust these if the account name or paths ever change
#1747680855
SBOX=/home/notesao/sandbox
#1747680855
TEST=/home/notesao/ffltest
#1747680855
OUT=/home/notesao/patch_work/core_deltas.patch    # where you want the diff
#1747680855
# 1) create the output directory if it doesn’t exist
#1747680855
mkdir -p "$(dirname "$OUT")"
#1747680855
# 2) make a list of just the files you care about
#1747680855
cat > /tmp/filelist.txt <<'EOF'
public_html/helpers.php
public_html/home.php
public_html/navbar.php
public_html/auth.php
public_html/client-update.php
public_html/client-image-upload.php
sql_functions.php
EOF

#1747680855
# 3) build the diff   ( --new-file         == show adds/deletes
#1747680855
#                       --binary           == handle binaries safely
#1747680855
#                       --exclude='.git'   == ignore VCS clutter )
#1747680855
diff -uNr --binary --new-file      --exclude='.git' --exclude='vendor'       $(sed "s|^|$SBOX/|" /tmp/filelist.txt)       $(sed "s|^|$TEST/|" /tmp/filelist.txt)       > "$OUT" 2>/dev/null          # squelch harmless permission‑denied msgs
#1747680855
# 4) confirm
#1747680855
echo "Wrote $(wc -l < "$OUT") lines to $OUT"
#1747680920
diff -uNr --binary --new-file      --exclude='.git' --exclude='vendor'      --exclude='*/logs/*' --exclude='*error_log'      --exclude='*.{png,ico,jpg,jpeg,pdf,docx,zip}'      /home/notesao/sandbox /home/notesao/ffltest      > /home/notesao/patch_work/full_sandbox_vs_ffltest.patch 2>/dev/null
#1747756037
chmod +x /home/notesao/sandbox/sandbox_update.sh && cd /home/notesao/sandbox && bash -x ./sandbox_update.sh 2>&1 | tee /tmp/sandbox_update_$(date +%F_%H%M).log
#1747756059
chmod +x /home/notesao/sandbox/scripts/sandbox_update.sh && cd /home/notesao/sandbox && bash -x ./sandbox_update.sh 2>&1 | tee /tmp/sandbox_update_$(date +%F_%H%M).log
#1747756165
chmod +x /home/notesao/sandbox/scripts/sandbox_update.sh
#1747756170
cd /home/notesao/sandbox/scripts
#1747756175
bash -x ./sandbox_update.sh 2>&1 | tee /tmp/sandbox_update_$(date +%F_%H%M).log
#1747756305
ls -lh /home/notesao/sandbox/backups/
#1747926497
gunzip -c ~/sandbox/backups/sandbox_backup_pre_update_2025-05-22.sql.gz \ | mysql -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g' clinicnotepro_sandbox
#1747926521
gunzip -c /home/notesao/sandbox/backups/sandbox_backup_pre_update_2025-05-22.sql.gz \ | mysql -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g' clinicnotepro_sandbox
#1747926547
# example paths – adjust to match your file names / dates
#1747926547
gunzip -c ~/sandbox/backups/client_pre_scramble.sql.gz   | mysql -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g' clinicnotepro_sandbox
#1747926640
# list everything with dates / sizes so it’s easy to pick out the right file
#1747926640
ls -lh ~/sandbox/backups
#1747926674
# one long command (wraps here just for readability)
#1747926674
gunzip -c ~/sandbox/backups/sandbox_backup_pre_update_2025-05-22.sql.gz   | mysql -h localhost -u clinicnotepro_sandbox_app           -p'PF-m[T-+pF%g' clinicnotepro_sandbox
#1748012493
diff -rupN      --exclude='*.log'      --exclude='*.csv'      --exclude='*.pdf'      /home/notesao/ffltest /home/notesao/sandbox      > /home/notesao/ffltestvsandbox.txt
#1748013178
diff -rupN      --exclude='logs'           --exclude='*.log'          --exclude='*.csv'          --exclude='*.pdf'          /home/notesao/ffltest  /home/notesao/sandbox      > /home/notesao/ffltestvsandbox.txt
#1748013246
diff -rupN      --exclude='logs'      \        # skips every scripts/logs/ directory
#1748013246
     --exclude='error_log' \        # skips public_html/error_log files
#1748013246
     --exclude='*.log'          --exclude='*.csv'          --exclude='*.pdf'          /home/notesao/ffltest  /home/notesao/sandbox      > /home/notesao/ffltestvsandbox.txt
#1748013288
diff -rupN   --exclude=logs   --exclude=error_log   --exclude='*.log'   --exclude='*.csv'   --exclude='*.pdf'   /home/notesao/ffltest /home/notesao/sandbox   > /home/notesao/ffltestvsandbox.txt
#1749233425
grep -RIn --color=auto "globalclinics" /home/notesao 2>/dev/null
#1749233723
grep -RIn --color=auto "/home/notesao/clinicpublic/config.php" /home/notesao 2>/dev/null
#1749235508
# From any location:
#1749235508
tree -a -L 2 /home/notesao/NotePro-Report-Generator
#1749236617
tail -f /home/notesao/NotePro-Report-Generator/fetch_data_errors.log
#1749242248
find /home/notesao -type d -name .git -prune -print | less
#1749243208
rm -rf /home/notesao/denton/.git
#1749665083
php /home/notesao/ffltest/public_html/mar2.php > ~/mar2_output.html
#1749665109
php -d variables_order=EGPCS     -r 'parse_str("action=Generate&start_date=2025-05-01&end_date=2025-05-31&program_id=2", $_POST); include "/home/notesao/ffltest/public_html/mar2.php";' > ~/mar2_may_output.html
#1749745756
ps aux | grep apache2
#1749745761
ps aux | grep -E 'nginx|php-fpm'
#1749745812
sudo ls -l /proc/1553412/fd
#1749745829
sudo cat /proc/1553412/cmdline | tr '\0' ' '
#1749745844
sudo kill -9 1553412
#1749745891
tail -n 100 /home/notesao/logs/ctc_notesao_com.php.error.log
#1749746465
ps -eo pid,etime,pcpu,pmem,cmd | grep 'php-fpm: pool ctc_notesao_com' | grep -v grep
#1749747020
# try graceful first …
#1749747020
sudo kill -SIGTERM 1554698 1554781
#1749747040
tail -n 100 /home/notesao/logs/ctc_notesao_com.php.error.log
#1749747132
ps -eo pid,etime,pcpu,pmem,cmd | grep 'php-fpm: pool ctc_notesao_com' | grep -v grep
#1749747145
sudo kill -SIGTERM 1570702
#1749747225
tail -n 100 /home/notesao/logs/ctc_notesao_com.php.error.log
#1749747349
ps -eo pid,etime,pcpu,pmem,cmd | grep 'php-fpm: pool ctc_notesao_com' | grep -v grep
#1749747357
sudo kill -SIGTERM 1571851
#1749747368
tail -n 100 /home/notesao/logs/ctc_notesao_com.php.error.log
#1749756140
ps -eo pid,etime,pcpu,pmem,cmd | grep 'php-fpm: pool ctc_notesao_com' | grep -v grep
#1749756150
sudo kill -SIGTERM 1572799
#1749756167
tail -n 100 /home/notesao/logs/ctc_notesao_com.php.error.log
#1749756283
ps -eo pid,etime,pcpu,pmem,cmd | grep 'php-fpm: pool ctc_notesao_com' | grep -v grep
#1749756300
sudo kill -SIGTERM 1628147
#1749756316
ps -eo pid,etime,pcpu,pmem,cmd | grep 'php-fpm: pool ctc_notesao_com' | grep -v grep
#1749756323
tail -n 100 /home/notesao/logs/ctc_notesao_com.php.error.log
#1749777451
scp -P 522 -r notesao@50.28.37.79:/home/notesao/NotePro-Report-Generator ~/Downloads/
#1749777628
scp -P 522 -r notesao@50.28.37.79:/home/notesao/NotePro-Report-Generator ~/Downloads/generator/
#1749777722
ls -al ~/Downloads/generator/
#1749823573
tree -a -L 3 /home/notesao
#1749823619
tree -a -L 3 /home/notesao > /home/notesao/notesao_structure.txt
#1749839728
grep -Rni --color=auto -E 'ffl|free[[:space:]]+for[[:space:]]+life' /home/notesao/bestoption/
#1749839769
grep -Rni --color=auto      --exclude='*.log'      --exclude='*.log.*'      --exclude='*.gz'      --exclude-dir='log'      --exclude-dir='logs'      -E 'ffl|free[[:space:]]+for[[:space:]]+life'      /home/notesao/bestoption/
#1749839798
grep -Rni --color=auto --exclude='*.log' --exclude='*.log.*' --exclude='*.gz' --exclude-dir='log' --exclude-dir='logs' -E 'ffl|free[[:space:]]+for[[:space:]]+life' /home/notesao/bestoption/
#1749840448
grep -RIn --binary-files=without-match --color=auto      --exclude-dir={.git,log,logs} --exclude='*.log*'      --include='*.{php,js,ts,jsx,tsx,py,sh,pl,rb,html,css}'      -E 'ffl|free[[:space:]]+for[[:space:]]+life' /home/notesao/bestoption/
#1749840519
grep -RIn --binary-files=without-match --color=auto      --exclude-dir={.git,log,logs}      --exclude='*error_log*' --exclude='*.log' --exclude='*.log.*'      --include='*.{php,inc,js,ts,jsx,tsx,py,sh,pl,rb,html,css,sql}'      -E 'ffl|free[[:space:]]+for[[:space:]]+life' /home/notesao/bestoption/
#1749840626
grep -RIn --binary-files=without-match --color=auto      --exclude-dir={.git,log,logs}      --exclude='*error_log*' --exclude='*.log*'      --include='*.{php,inc,js,ts,jsx,tsx,py,sh,pl,rb,html,css,sql}'      -i -E 'ffl|free[[:space:]]+for[[:space:]]+life' /home/notesao/bestoption/
#1749840727
grep -RIn --binary-files=without-match --color=always      --exclude-dir={.git,log,logs}      --exclude='*error_log*' --exclude='*.log*'      --include='*.{php,inc,js,ts,jsx,tsx,py,sh,pl,rb,html,css,sql}'      -i -E 'ffl|free[[:space:]]+for[[:space:]]+life' /home/notesao/bestoption/   | LC_ALL=C sort -f
#1749841074
mkdir -p /home/notesao/bestoption && rsync -a /home/notesao/ffltest/ /home/notesao/bestoption/
#1749841087
grep -RIn --binary-files=without-match --color=always      --exclude-dir={.git,log,logs}      --exclude='*error_log*' --exclude='*.log*'      --include='*.{php,inc,js,ts,jsx,tsx,py,sh,pl,rb,html,css,sql}'      -i -E 'ffl|free[[:space:]]+for[[:space:]]+life' /home/notesao/bestoption/   | LC_ALL=C sort -f
#1750098900
apachetcl -S
#1750099091
curl -svI http://50.28.37.79/ 2>&1 | head -20
#1750099099
grep -RIl --exclude-dir={.git,logs} "Report Generator" ~/public_html 2>/dev/null
#1750099105
stat -c '%n %y' "$(readlink -f /path/to/file/from/grep)"
#1750099152
ss -ltnp 2>/dev/null | grep ':80 ' || netstat -ltnp 2>/dev/null | grep ':80 '
#1750099159
pgrep -af gunicorn
#1750099164
grep -RIl "Report Generator" /home/notesao 2>/dev/null | head
#1750099180
apachectl -S 2>&1 | head -15
#1750099303
mkdir -p /etc/apache2/conf.d/userdata/std/2_4/_default/
#1750099330
sudo mkdir -p /etc/apache2/conf.d/userdata/std/2_4/_default/
#1750099403
echo 'RedirectMatch 302 ^/(.*)$ https://notesao.com/$1'  | sudo tee /etc/apache2/conf.d/userdata/std/2_4/_default/redirect-ip.conf >/dev/null
#1750099416
sudo /scripts/ensure_vhost_includes --all-users
#1750099422
sudo apachectl graceful
#1750099442
curl -I http://50.28.37.79/
#1750099474
sudo ss -pltn | grep ':80'
#1750099483
sudo lsof -iTCP:80 -sTCP:LISTEN -P -n
#1750099512
# look through all cPanel include-files for “8002” or “ProxyPass /”
#1750099512
sudo egrep -RInH '8002|ProxyPass[[:space:]]+/|RewriteRule[[:space:]]+\^/.*\[P'      /etc/apache2/conf.d/includes /etc/apache2/conf.d/*.conf
#1750099540
sudo cp /etc/apache2/conf.d/includes/pre_main_global.conf         /etc/apache2/conf.d/includes/pre_main_global.conf.bak
#1750099540
sudo cp /etc/apache2/conf.d/report.notepro.co.conf         /etc/apache2/conf.d/report.notepro.co.conf.bak
#1750099544
sudo nano /etc/apache2/conf.d/includes/pre_main_global.conf
#1750099574
sudo nano /etc/apache2/conf.d/report.notepro.co.conf
#1750099607
sudo /scripts/ensure_vhost_includes --all-users   # cPanel helper
#1750099607
sudo apachectl configtest                         # should say “Syntax OK”
#1750099608
sudo apachectl graceful                           # reload without downtime
#1750099616
curl -I http://50.28.37.79/
#1750099653
sudo nano /etc/apache2/conf.d/report.notepro.co.conf
#1750099702
sudo mv /etc/apache2/conf.d/report.notepro.co.conf         /etc/apache2/conf.d/report.notepro.co.conf.disabled
#1750099702
sudo /scripts/ensure_vhost_includes --all-users
#1750099703
sudo apachectl configtest     # should say “Syntax OK”
#1750099703
sudo apachectl graceful
#1750099707
curl -I  http://50.28.37.79/       # should now give your 302 → notesao.com
#1750099775
sudo nano /etc/apache2/conf.d/notepro.co.conf          # file name may differ
#1750099812
sudo apachectl -S | less
#1750099907
sudo nano +314 /etc/apache2/conf/httpd.conf
#1750100287
sudo mkdir -p /etc/apache2/conf.d/userdata/std/2_4/_default
#1750100287
sudo tee  /etc/apache2/conf.d/userdata/std/2_4/_default/00-redirect-ip.conf >/dev/null <<'EOF'
<VirtualHost 50.28.37.79:80>
    ServerName 50.28.37.79
    Redirect 302 / https://notesao.com/
</VirtualHost>
EOF

#1750100295
sudo /scripts/ensure_vhost_includes --all-users   # writes the new include paths
#1750100295
sudo apachectl configtest                         # should say “Syntax OK”
#1750100295
sudo apachectl graceful                           # reloads Apache
#1750100298
curl -I http://50.28.37.79/
#1750100298
# → 302 Location: https://notesao.com/
#1750101452
sudo /usr/local/cpanel/bin/dbmaptool clinicnotepro       --type mysql  --dbs 'clinicnotepro_bestoption'
#1750101468
sudo /usr/local/cpanel/bin/dbmaptool clinicnotepro       --type mysql  --dbusers 'clinicnotepro_bestoption_app'
#1750101477
/usr/local/cpanel/bin/update_db_cache --force clinicnotepro
#1750101591
sudo -i
#1750101642
# double-check the YAML now contains the DB
#1750101642
sudo grep -R clinicnotepro_bestoption /var/cpanel/databases/clinicnotepro.yaml
#1750101682
# still as the *normal* user is fine:
#1750101682
sudo /usr/local/cpanel/bin/dbmaptool clinicnotepro --type mysql --list
#1750101721
sudo -i                               # become root
#1750190722
sudo grep -R --line-number --color=auto -E "^\s*disable_functions\s*=.*shell_exec"      /etc/php* /opt/cpanel/ea-php*/root/etc /home/notesao 2>/dev/null
#1750190819
sudo -u notesao which php
#1750190848
php -i | grep -E "Loaded Configuration File|Scan this dir"
#1750190855
sudo grep -n --color=auto -E "^\s*disable_functions\s*=.*shell_exec"      /opt/cpanel/ea-php81/root/etc/php.ini      /opt/cpanel/ea-php81/root/etc/php.d/*.ini 2>/dev/null
#1750190889
# run exactly as shown
#1750190889
sudo grep -n --color=auto -E "^\s*disable_functions\s*=.*\bshell_exec\b"      /opt/cpanel/ea-php81/root/etc/php.ini      /opt/cpanel/ea-php81/root/etc/php.d/*.ini 2>/dev/null
#1750190923
sudo grep -R -n --color=auto -E "disable_functions.*\bshell_exec\b"      /opt/cpanel/ea-php81/root/etc/php-fpm.d 2>/dev/null
#1750190954
# back-up the active pool file
#1750190954
sudo cp /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf         /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.bak-$(date +%F)
#1750190954
# open it in nano (or vim)
#1750190954
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf
#1750190981
sudo systemctl reload ea-php81-php-fpm
#1750355077
sudo grep -R "disable_functions" /etc/php /opt/cpanel /usr/local/lib/php.ini /usr/local/etc/php /usr/local/php /home/*/php.ini 2>/dev/null
#1750355110
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf
#1750355176
sudo systemctl restart ea-php81-php-fpm
#1750355181
sudo systemctl status ea-php81-php-fpm
#1750355255
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf
#1750355288
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf
#1750355309
/opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf
#1750355314
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf
#1750355340
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf
#1750355343
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf
#1750355361
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf
#1750355384
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf
#1750355403
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf
#1750355415
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf
#1750355437
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf
#1750355480
sudo nano /opt/cpanel/ea-php81/root/etc/php.ini
#1750355541
sudo nano /opt/cpanel/ea-php81/root/etc/php.d/ssp.ini
#1750355559
sudo systemctl restart ea-php81-php-fpm
#1750880128
cd /home/notesao && { command -v tree >/dev/null   && tree -a -F --dirsfirst   || find . -print; } > /home/notesao/notesao_structure.txt
#1750880470
cd /home/notesao && tree -a -F --dirsfirst -I 'logs|log|*.log|tmp|cache|vendor|node_modules|.git|*.zip|*.gz|*.tar|*.sql|*.csv|*.pdf'    > /home/notesao/notesao_structure_clean.txt
#1750880533
cd /home/notesao && find .   \( -path './logs' -o -path './logs/*'      -o -path './log'  -o -path './log/*'      -o -name '*.log'      -o -path './tmp'  -o -path './tmp/*'      -o -path './cache' -o -path './cache/*'      -o -path './vendor' -o -path './vendor/*'      -o -path './node_modules' -o -path './node_modules/*'      -o -path './.git' -o -path './.git/*'      -o -name '*.zip' -o -name '*.gz' -o -name '*.tar'      -o -name '*.sql' -o -name '*.csv' -o -name '*.pdf' \) -prune -o -print   > /home/notesao/notesao_structure_clean.txt
#1750880606
cd /home/notesao/public_html/ && tree -a -F --dirsfirst -I 'logs|log|*.log|tmp|cache|vendor|node_modules|.git|*.zip|*.gz|*.tar|*.sql|*.csv|*.pdf'    > /home/notesao/notesao_structure_clean.txt
#1750880710
cd /home/notesao && tree -a -F --dirsfirst   -I 'logs|log|*.log|tmp|cache|vendor|node_modules|.git|*.zip|*.gz|*.tar|*.sql|*.csv|*.pdf|bestoption|dwag|safatherhood|sandbox|transform'   > /home/notesao/notesao_structure_clean.txt
#1750880818
cd /home/notesao && tree -a -F --dirsfirst   -I 'logs|log|*.log|tmp|cache|vendor|node_modules|.git|*.zip|*.gz|*.tar|*.sql|*.csv|*.pdf|bestoption|dwag|safatherhood|sandbox|transform|Downloads'   > /home/notesao/notesao_structure_clean.txt
#1750880906
# 1) Make the lib directory
#1750880906
mkdir /home/notesao/lib
#1750880906
# 2) Install PHPMailer (if you haven’t yet)
#1750880906
cd /home/notesao
#1750880906
composer require phpmailer/phpmailer
#1750880974
nano /home/notesao/lib/mailer.php
#1750881048
nano /home/notesao/public_html/forgot_password.php
#1750952443
cd /home/notesao
#1750952452
composer require phpmailer/phpmailer
#1750952506
nano /home/notesao/public_html/reset_password.php
#1750952636
# show the last 50 error-log lines written by forgot_password.php & mailer.php
#1750952636
tail -n 50 /home/notesao/public_html/forgot_password.error.log 2>/dev/null   || tail -n 50 $(php -r 'echo ini_get("error_log");') 2>/dev/null
#1750952684
tail -n 50 /home/notesao/public_html/forgot_password.error.log
#1750952709
php -r "
require '/home/notesao/vendor/autoload.php';
require '/home/notesao/global_config.php';
use PHPMailer\PHPMailer\PHPMailer;

try {
    \$mail = new PHPMailer(true);
    \$mail->SMTPDebug  = 3;                 // VERY verbose
    \$mail->Debugoutput = function(\$str) { echo \$str; };  // print to terminal

    \$mail->isSMTP();
    \$mail->Host       = smtp_host;
    \$mail->SMTPAuth   = true;
    \$mail->Username   = smtp_user;
    \$mail->Password   = smtp_pass;
    \$mail->SMTPSecure = smtp_secure;       // 'tls' or 'ssl'
    \$mail->Port       = smtp_port;

    \$mail->setFrom(smtp_from, smtp_from_name);
    \$mail->addAddress('you@example.com');  // <-- put any real address you can read
    \$mail->Subject = 'NotesAO SMTP test';
    \$mail->Body    = 'If you see this, SMTP worked.';

    \$mail->send();
    echo \"\\nMAIL SENT SUCCESSFULLY\\n\";
} catch (Exception \$e) {
    echo \"\\nERROR: \" . \$mail->ErrorInfo . \"\\n\";
}"
#1750957945
tail -n 50 /home/notesao/ffltest/public_html/forgot_password.error.log 2>/dev/null
#1750957995
php -r 'echo "PHP error_log = ", ini_get("error_log"), PHP_EOL;'
#1750958127
# show the 10 most recently-modified error_log files
#1750958127
find /home/notesao/ffltest -name error_log -printf '%TY-%Tm-%Td %TH:%TM %p\n'   | sort -r | head
#1750958137
tail -n 100 /home/notesao/ffltest/public_html/admin/error_log
#1750958164
tail -n 100 /home/notesao/ffltest/public_html/error_log
#1750958636
# run from any folder
#1750958636
php -r "
require '/home/notesao/lib/mailer.php';
send_email('you@example.com','SMTP trace','If you see this, SMTP worked.')
    ? print(\"sent\\n\")
    : print(\"fail\\n\");
"
#1750958651
php -r "
require '/home/notesao/lib/mailer.php';
send_email('admin@notesao.com','SMTP trace','If you see this, SMTP worked.')
    ? print(\"sent\\n\")
    : print(\"fail\\n\");
"
#1750958663
# look at the last few lines the CLI just wrote
#1750958663
tail -n 20 ./error_log
#1750959012
# from the clinic folder
#1750959012
grep -n "send_email(" admin/accounts.php
#1750959038
grep -n "send_email("/home/notesao/ffltest/public_html/admin/accounts.php
#1750959102
# 1) Go to the folder that really holds accounts.php
#1750959102
cd /home/notesao/ffltest/public_html/admin
#1750959102
# 2) Search for every call to send_email() and show the line numbers
#1750959103
grep -n "send_email(" accounts.php
#1750958936
tail -f /home/notesao/ffltest/public_html/admin/error_log
#1750959470
# folder the public site points to
#1750959471
tail -n 40 /home/notesao/public_html/error_log
#1750959475
tail -n 40 /home/notesao/ffltest/public_html/error_log
#1750959664
# main domain’s PHP error log – try this first
#1750959664
tail -n 40 /home/notesao/error_log
#1750959678
# sometimes Apache writes fatals here
#1750959678
grep -i "activate.php" -R /home/notesao | head
#1750959763
ls -l /home/notesao/public_html/activate.php
#1750959763
# very likely → “No such file or directory”
#1750959775
php -l /home/notesao/public_html/activate.php
#1750959834
# the main vhost error log is usually here on cPanel boxes
#1750959834
tail -f /usr/local/apache/logs/error_log | grep -i activate.php
#1750960100
tail -f /home/notesao/public_html/error_log      # root site
#1750960100
tail -f /home/notesao/ffltest/public_html/error_log   # clinic site
#1750960167
# main vhost log           (most cPanel boxes)
#1750960167
tail -f /usr/local/apache/logs/error_log
#1750960249
# root-site PHP errors for notesao.com
#1750960249
tail -f /home/notesao/error_log
#1752089802
curl -sIL https://ffl.notesao.com | grep -i "Strict-Transport-Security"
#1752092141
mysql -u root -p -e "SHOW DATABASES LIKE 'clinicnotepro_%';"
#1752157927
cd /home/notesao/ffltest/public_html && grep -Eo "(include|include_once|require|require_once)[^;]+" home.php   | sed -E "s/(include(_once)?|require(_once)?)[[:space:]]*\(?[[:space:]]*['\"]([^'\"]+)['\"].*/\4/"   | sort -u
#1752157975
cd /home/notesao/ffltest/public_html && grep -Eo "require(_once)?[[:space:]]*\([[:space:]]*['\"][^'\"]+['\"]|include(_once)?[[:space:]]*\([[:space:]]*['\"][^'\"]+['\"]" home.php   | sed -E "s/.*['\"]([^'\"]+)['\"].*/\1/"   | sort -u
#1752158152
cd /home/notesao/ffltest/public_html && (   queue="auth.php navbar.php"; declare -A seen;   while [ -n "$queue" ]; do     set -- $queue; file=$1; queue="${queue#"$file"}"; queue="${queue#" "}";     [[ -z $file || -n ${seen[$file]} ]] && continue; seen[$file]=1;     echo "$file";     includes=$(grep -Eo "(require|include)(_once)?[[:space:]]*\([[:space:]]*['\"][^'\"]+['\"]" "$file" 2>/dev/null \
               | sed -E "s/.*['\"]([^'\"]+)['\"].*/\1/");     queue="$queue $includes";   done; ) | sort -u
#1752158657
cd /home/notesao/ffltest && grep -RIl --exclude-dir={vendor,node_modules,logs,cache}     -E "(FROM|JOIN|UPDATE|INTO)[[:space:]]+\`?accounts\`?"     --include="*.php" . | sort -u
#1752159406
cd /home/notesao/
#1752162255
# lists every file & folder (hidden files too) under /home/notesao/adminclinic
#1752162255
# and drops it in a text file you can scroll or share
#1752162255
cd /home/notesao/adminclinic && tree -a -F --dirsfirst > /home/notesao/adminclinic_structure.txt
#1752162255
# view it
#1752162255
less /home/notesao/adminclinic_structure.txt
#1752165824
cd /home/notesao/adminclinic && tree -a -F --dirsfirst > /home/notesao/adminclinic_structure.txt
#1752165826
less /home/notesao/adminclinic_structure.txt
#1752259330
php -i | grep -iE '^(disable_functions|Loaded Configuration File)'
#1752259343
grep -Rns --color=auto --exclude-dir={vendor,node_modules} -E '\bexec\s*\('     /home/notesao/sandbox/public_html
#1752259418
grep -Rns --color=auto -E '^\s*disable_functions\s*=.*\bexec\b'     /opt/cpanel/ea-php*/root/etc 2>/dev/null
#1752259428
find /home/notesao \( -name '*.ini' -o -name '.user.ini' \) -type f -print0 | xargs -0 grep -n --color=auto -E '^\s*disable_functions\s*=.*\bexec\b'
#1752259467
##############################################################################
#1752259467
# 1) Show every FPM pool file that disables exec()
#1752259467
##############################################################################
#1752259467
grep -Rns --color=auto -E '^\s*php_admin_value\s+disable_functions\s*=.*\bexec\b'     /opt/cpanel/ea-php*/root/etc/php-fpm.d 2>/dev/null
#1752259478
##############################################################################
#1752259478
# 2) Check all additional *.ini files that FPM loads for this PHP version
#1752259478
##############################################################################
#1752259478
grep -Rns --color=auto -E '^\s*disable_functions\s*=.*\bexec\b'     /opt/cpanel/ea-php*/root/etc/php.d 2>/dev/null
#1752259554
sudo grep -Rns --color=auto -E '^\s*(php_(admin|value)\s+)?disable_functions\s*=.*' / 2>/dev/null
#1752259584
grep -Rns --color=auto -E '^\s*(php_(admin|value)\s+)?disable_functions\s*=.*' $HOME /opt 2>/dev/null
#1752259745
sudo grep -Rns --color=auto -E     '(php_admin_value\[?disable_functions\]?|disable_functions)\s*=\s*[^#\n]*\bexec\b'     /etc /opt /home/notesao 2>/dev/null
#1752259793
nano /home/notesao/sandbox/public_html/.user.ini
#1752259815
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf
#1752259837
sudo systemctl restart ea-php81-php-fpm
#1752262066
sudo grep -Rns --color=auto -E     '(php_admin_value\[?disable_functions\]?|disable_functions)\s*=\s*[^#\n]*\bexec\b'     /etc /opt /home/notesao 2>/dev/null
#1752262484
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf
#1752262552
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf
#1752262572
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf
#1752262595
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.notesao.com.conf
#1752262620
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notepro.co.conf
#1752262642
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf
#1752262664
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf
#1752262693
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf
#1752262716
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf
#1752262737
/opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf
#1752262741
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf
#1752262763
/opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf
#1752262766
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf
#1752262789
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf
#1752262811
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf
#1752262835
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf
#1752262855
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/sandbox.clinic.notepro.co.conf
#1752262877
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/clinic.notepro.co.conf
#1752262903
sudo systemctl restart ea-php81-php-fpm
#1752262905
sudo systemctl restart ea-php82-php-fpm
#1752262912
sudo grep -Rns --color=auto -E   'php_admin_value\[?disable_functions\]? = .*exec|shell_exec'   /opt/cpanel/ea-php81/root/etc/php-fpm.d   /opt/cpanel/ea-php82/root/etc/php-fpm.d
#1752262982
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf
#1752262997
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf
#1752263321
sudo dnf makecache
#1752263542
sudo grep -Rns --color=auto -E   'php_admin_value\[?disable_functions\]? = .*exec|shell_exec'   /opt/cpanel/ea-php81/root/etc/php-fpm.d   /opt/cpanel/ea-php82/root/etc/php-fpm.d
#1752434339
cd ~          # go to your home directory
#1752434339
pwd           # just to be sure where you are
#1752434347
cd public_html   # this is the document-root for https://notesao.com
#1752434347
ls -a            # list *all* files, including dotfiles
#1752434366
cd /home/notesao/public_html
#1752434366
nano .htaccess          # (or use vi if you prefer)
#1752434404
php -r 'echo getenv("RECAPTCHA_SECRET"), PHP_EOL;'
#1752434470
cd /home/notesao/public_html
#1752434470
nano .htaccess
#1752435284
sudo grep -A2 -B2 "NotesAO Interest Lead" /var/log/exim_mainlog | tail -n 40
#1752435520
grep -A2 -B2 'NotesAO Interest Lead' /var/log/exim_mainlog | tail -n 20
#1752435576
exim -bp | exiqsumm
#1752435595
sudo grep -A2 -B2 "NotesAO Interest Lead" /var/log/exim_mainlog | tail -n 40
#1752768822
mysql -uroot -e "
  SHOW DATABASES LIKE 'clinicnotepro_sage';
  SHOW DATABASES LIKE 'clinicnotepro_lankford';
"
#1752768888
mysql -uroot -p'6Ydlwg90Tb-wt7' -e "SHOW DATABASES LIKE 'clinicnotepro_sage'; SHOW DATABASES LIKE 'clinicnotepro_lankford';"
#1752768938
ls /var/cpanel/users
#1752768996
sudo -i
#1752789063
OUT="/home/notesao/directory_overview_$(date +%Y%m%d).txt"
#1752789063
{   echo "NotesAO directory overview – generated $(date)"; echo;    echo "──────────────────────────────────────────────────────────────";   echo "1. /home/notesao/ffltest/";   echo "   • Clinic‑specific web root for the FFLTEST clinic.";   echo "   • Contains PHP scripts, assets, and cron jobs that are";   echo "     unique to that clinic (e.g., client‑create.php, custom";   echo "     branding, favicon logic, nightly DB‑backup scripts).";   echo "   • Mirrors the public_html layout but is sandboxed to the";   echo "     clinic’s own database (clinicnotepro_ffltest).";   echo;   tree -L 2 /home/notesao/ffltest/;   echo; echo;    echo "──────────────────────────────────────────────────────────────";   echo "2. /home/notesao/NotePro-Report-Generator/";   echo "   • Centralised Flask / PHP hybrid that builds MAR, progress";   echo "     reports, BIPP letters, CSV dumps, etc.";   echo "   • Key sub‑folders:";   echo "       templates/   – Jinja & PHP templates per clinic";   echo "       csv/         – Auto‑generated CSVs, {Clinic}_report*.csv";   echo "       scripts/     – Python helpers (Celery tasks, LibreOffice";   echo "                      conversions, behaviour‑contract scripts)";   echo "       static/      – Shared JS (Chart.js helpers), CSS, logos";   echo "   • Gunicorn + Celery run from here (port 8002 by default).";   echo;   tree -L 2 /home/notesao/NotePro-Report-Generator/;   echo; echo;    echo "──────────────────────────────────────────────────────────────";   echo "3. /home/notesao/public_html/";   echo "   • Primary Apache/Nginx document root.";   echo "   • Holds the main NotesAO (notesao.com) front‑end, plus";   echo "     shared resources every sub‑domain can symlink to.";   echo "   • Typical contents:";   echo "       index.php         – marketing / landing page";   echo "       auth/             – global authentication helpers";   echo "       client.php        – now migrated to per‑clinic sub‑domains";   echo "       assets/           – global CSS/JS/fonts";   echo "       .well-known/      – ACME challenges for SSL";   echo "       cron/             – shell & PHP maintenance scripts";   echo;   tree -L 2 /home/notesao/public_html/;   echo; } > "$OUT"
#1752789063
echo "Overview written to: $OUT"
#1752853911
# Show the main php.ini the CLI is using
#1752853911
php -i | grep -E 'Loaded Configuration File'
#1752853911
# Show the directory PHP scans for additional .ini/.conf snippets
#1752853911
php -i | grep 'Scan this dir'
#1752853911
# Show the full, merged disabled_functions list seen by the CLI
#1752853911
php -i | grep disabled_functions
#1752853999
php -i | grep disable_functions
#1752854021
sudo grep -Rin --color      --include='*.ini'      -E '^[[:space:]]*disable_functions\s*=.*(exec|shell_exec|system|passthru|popen|proc_open)'      /opt/cpanel/ea-php81/root/etc 2>/dev/null
#1752854080
sudo grep -Rin --color 'disable_functions'      /opt/cpanel/ea-php81/root/etc/php-fpm.d 2>/dev/null
#1752854181
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf
#1752854298
# List only .conf files for dwag that PHP‑FPM actually reads
#1752854298
sudo ls /opt/cpanel/ea-php*/root/etc/php-fpm.d | grep -E '^dwag\..*\.conf$'
#1752854340
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf
#1752854376
sudo /scripts/restartsrv_php-fpm        # cPanel helper
#1752854376
# or, generic:
#1752854376
# sudo systemctl reload ea-php81-php-fpm
#1752854656
sudo grep -Rin --color      --include='*.ini' --include='*.conf'      -E '(^|php_(admin_)?value\[?)disable_functions[[:space:]]*=[^#;]*\b(exec|shell_exec|system|passthru|popen|proc_open)\b'      /opt/cpanel/ea-php* /etc/php* 2>/dev/null
#1752854665
# .user.ini or php.ini placed in dwag or parent dirs
#1752854665
find /home/notesao -type f \( -name '.user.ini' -o -name 'php.ini' \) -print -exec grep -Hn 'disable_functions' {} \;
#1752854665
# .htaccess style overrides (Apache only):
#1752854665
grep -Rin --color 'php_value[[:space:]]\+disable_functions' /home/notesao 2>/dev/null
#1752854782
grep -Hn 'disable_functions'    /home/notesao/dwag/public_html/{.user.ini,php.ini}
#1752854823
grep -Hn 'disable_functions' /home/notesao/dwag/public_html/{.user.ini,php.ini}
#1752854906
grep -RHIin --color 'disable_functions' /home/notesao/dwag 2>/dev/null
#1752854962
sudo grep -RHIin --color      -E '(^|\[|\s)(disable_functions|php_value\[?disable_functions)\s*=\s*[^#;\n]*\b(exec|shell_exec)\b'      /etc/php*      /opt/cpanel/ea-php*/root/etc      /home/notesao 2>/dev/null
#1752855056
echo '<?php phpinfo();' | sudo tee /home/notesao/dwag/public_html/info.php
#1752855061
curl -s https://dwag.notesao.com/info.php | grep -E 'PHP Version|Server API|Loaded Configuration|disable_functions' | head
#1752855229
sudo grep -RHIin --color      -E 'disable_functions[^=]*=[[:space:]]*[^#;\n]*\b(exec|shell_exec)\b'      /etc/php*      /opt/cpanel/ea-php*/root/etc      /home/notesao 2>/dev/null
#1752855365
sudo sed -i   -E 's/php_admin_value\[disable_functions\] = ([^#]*)(exec,?|,?shell_exec)//g'   /opt/cpanel/ea-php81/root/etc/php-fpm.d/*.conf
#1753993023
cd /home/notesao/sandbox/backups
#1753993034
mv sandbox_backup_post_update_2025-07-31.sql.gz    sandbox_backup_post_update_2025-07-31_pre-fix.sql.gz
#1753993047
mysqldump --single-transaction --quick --skip-triggers   -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g'   clinicnotepro_sandbox | gzip > sandbox_backup_post_update_2025-07-31.sql.gz
#1753993055
ls -lh sandbox_backup_post_update_2025-07-31.sql.gz
#1753993055
# quick integrity check (header only)
#1753993055
gunzip -c sandbox_backup_post_update_2025-07-31.sql.gz | head
#1753993186
chmod +x /home/notesao/sandbox/scripts/sandbox_update.sh
#1753993192
/home/notesao/sandbox/scripts/sandbox_update.sh | tee ~/sandbox_update_$(date +%F_%H%M).log
#1753993269
gunzip -c /home/notesao/sandbox/backups/manual_pre_run_2025-07-31_*.sql.gz | mysql -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g' clinicnotepro_sandbox
#1753993303
cd /home/notesao/sandbox/backups
#1753993303
ls -lh *.sql.gz
#1753993341
cd /home/notesao/sandbox/backups
#1753993341
gunzip -c sandbox_backup_pre_update_2025-07-31.sql.gz | mysql -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g' clinicnotepro_sandbox
#1753993350
mysql -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g'   -e "SELECT program_id, COUNT(*) AS clients FROM client GROUP BY program_id;"   clinicnotepro_sandbox
#1754058326
cd /home/notesao/sandbox/backups
#1754058326
gunzip -c sandbox_backup_post_update_2025-07-31_pre-fix.sql.gz | mysql -h localhost         -u clinicnotepro_sandbox_app         -p'PF-m[T-+pF%g'         clinicnotepro_sandbox
#1754059513
cd /home/notesao/sandbox/backups
#1754059513
# remove the bad 108 KB dump
#1754059513
rm  sandbox_backup_post_update_2025-07-31.sql.gz
#1754059513
# promote the good one
#1754059513
mv  sandbox_backup_post_update_2025-07-31_pre-fix.sql.gz     sandbox_backup_post_update_2025-07-31.sql.gz
#1754060029
# from the directory that contains sandbox_update.sh
#1754060029
chmod +x sandbox_update.sh          # if not already executable
#1754060029
./sandbox_update.sh | tee ~/sandbox_update_test_$(date +%F_%H%M).log
#1754060053
# locate it
#1754060053
ls /home/notesao/sandbox/scripts 2>/dev/null | grep sandbox_update.sh
#1754060053
# or
#1754060053
ls /home/notesao/sandbox | grep sandbox_update.sh
#1754060082
# 1 – make sure it’s executable (only needed once)
#1754060082
chmod +x /home/notesao/sandbox/scripts/sandbox_update.sh
#1754060082
# 2 – execute and capture output in a log
#1754060082
/home/notesao/sandbox/scripts/sandbox_update.sh   | tee ~/sandbox_update_test_$(date +%F_%H%M).log
#1754060236
/home/notesao/sandbox/scripts/sandbox_update.sh   | tee ~/sandbox_update_test_fix_$(date +%F_%H%M).log
#1754060348
/home/notesao/sandbox/scripts/sandbox_update.sh   | tee ~/sandbox_update_test_fix2_$(date +%F_%H%M).log
#1754062782
grep -Eni '^\s*(insert|update|delete|alter|create|drop).*clinicnotepro_ffltest'         /home/notesao/sandbox/scripts/sandbox_update.sh
#1754062791
mysql -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g'   -e "SHOW GRANTS FOR CURRENT_USER\G"
#1754063653
/home/notesao/sandbox/scripts/sandbox_update.sh box_update_test_>   | tee ~/sandbox_update_test_fix2_$(date +%F_%H%M).log
#1754063662
/home/notesao/sandbox/scripts/sandbox_update.sh
#1754069431
chmod +x /home/notesao/sandbox/scripts/sandbox_update.sh   # one-time
#1754069431
/home/notesao/sandbox/scripts/sandbox_update.sh            # test run
#1754076657
chmod +x /home/notesao/sandbox/scripts/sandbox_update.sh    # only once
#1754076657
/home/notesao/sandbox/scripts/sandbox_update.sh   | tee ~/sandbox_update_test_$(date +%F_%H%M).log
#1754076730
/home/notesao/sandbox/scripts/sandbox_update.sh   | tee ~/sandbox_update_test_$(date +%F_%H%M).log
#1754076976
grep -Eni '^\s*(insert|update|delete|alter).*clinicnotepro_ffltest'      /home/notesao/sandbox/scripts/sandbox_update.sh
#1754076986
/home/notesao/sandbox/scripts/sandbox_update.sh   | tee ~/sandbox_update_test_autocomplete_$(date +%F_%H%M).log
#1754081144
# run as root
#1754081144
grep -R --exclude='*.bak' --exclude='*.sav' -n         --include='*.ini' --include='*.conf'         '^\s*disable_functions'         /etc/php* /etc/*php*/ /opt/cpanel/ea-php*/root/etc/ 2>/dev/null
#1754081239
# search every .ini that lives under /home, but skip backups
#1754081239
grep -R --exclude='*.bak' --exclude='*.sav' -n       --include='*.ini' --include='.user.ini'       '^\s*disable_functions'       /home 2>/dev/null
#1754081254
grep -R -n 'disable_functions' /home/notesao/sandbox/public_html 2>/dev/null
#1754081317
# run as root – prints   path:line#:line-content
#1754081317
grep -RIn --binary-files=without-match           --exclude='*.bak' --exclude='*.sav' --exclude='*.swp'           --exclude-dir='{proc,sys,dev,run,tmp,var/cache,var/lib,snap}'           '^\s*disable_functions' /
#1754081375
sudo # run as root
#1754081375
grep -RHIin     --exclude='*.bak' --exclude='*.sav' --exclude='*.swp'     --include='*.ini' --include='.user.ini' --include='*.conf'     --exclude-dir=proc --exclude-dir=sys --exclude-dir=dev --exclude-dir=run     --exclude-dir=tmp  --exclude-dir='var/cache' --exclude-dir='var/lib'     -e '^\s*disable_functions'     /etc /opt/cpanel/ea-php* /home 2>/dev/null
#1754081459
# run as root
#1754081459
grep -RHIin --binary-files=without-match     --exclude='*.bak' --exclude='*.sav' --exclude='*.swp'     --include='*.ini' --include='.user.ini' --include='*.conf'     --exclude-dir='{proc,sys,dev,run,tmp,var/cache,var/lib}'     -e '^\s*disable_functions.*\bexec\b'     /etc /opt/cpanel/ea-php* /home 2>/dev/null
#1754081469
sudo grep -RHIin --binary-files=without-match     --exclude='*.bak' --exclude='*.sav' --exclude='*.swp'     --include='*.ini' --include='.user.ini' --include='*.conf'     --exclude-dir='{proc,sys,dev,run,tmp,var/cache,var/lib}'     -e '^\s*disable_functions.*\bexec\b'     /etc /opt/cpanel/ea-php* /home 2>/dev/null
#1754081546
sudo grep -Rns --color=auto -E   'php_admin_value\[?disable_functions\]? = .*exec|shell_exec'   /opt/cpanel/ea-php81/root/etc/php-fpm.d   /opt/cpanel/ea-php82/root/etc/php-fpm.d
#1754081633
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf
#1754081670
sudo grep -Rns --color=auto -E   'php_admin_value\[?disable_functions\]? = .*exec|shell_exec'   /opt/cpanel/ea-php81/root/etc/php-fpm.d   /opt/cpanel/ea-php82/root/etc/php-fpm.d
#1754657972
grep -nH disable_functions     /opt/cpanel/ea-php{81,82}/root/etc/php-fpm.d/*.conf   | grep -E 'exec|shell_exec|shell_exe' || echo "✅ no exec/shell_exec left"
#1754657976
sudo grep -nH disable_functions     /opt/cpanel/ea-php{81,82}/root/etc/php-fpm.d/*.conf   | grep -E 'exec|shell_exec|shell_exe' || echo "✅ no exec/shell_exec left"
#1754658185
sudo grep -Rns --color=auto -E >   'php_admin_value\[?disable_functions\]? = .*exec|shell_exec' >   /opt/cpanel/ea-php81/root/etc/php-fpm.d >   /opt/cpanel/ea-php82/root/etc/php-fpm.d
#1754658201
sudo grep -Rns --color=auto -E   'php_admin_value\[?disable_functions\]?.*(exec|shell_exec|shell_exe)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/*safatherhood*.conf*
#1754658278
sudo grep -Rns --color=auto --include='*.conf' --include='*.ini'   -E '^\s*(php_admin_value\[?disable_functions\]?|disable_functions)\s*=.*exec'   /opt/cpanel
#1754658361
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf
#1754658391
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf
#1754658449
# 2) Reload the PHP-FPM service for ea-php81
#1754658449
sudo systemctl reload ea-php81-php-fpm 2>/dev/null || sudo /scripts/restartsrv_ea-php81-php-fpm
#1754670299
mysqldump --single-transaction --quick --skip-triggers   -h localhost -u clinicnotepro_sandbox_app -p'PF-m[T-+pF%g'   clinicnotepro_sandbox   client attendance_record absence ledger victim | gzip > /home/notesao/sandbox/backups/sandbox_backup_post_update_$(date +%F).sql.gz
#1754670313
cp /home/notesao/sandbox/backups/sandbox_backup_post_update_$(date +%F).sql.gz    /home/notesao/sandbox/backups/sandbox_backup_post_update_$(date -d yesterday +%F).sql.gz
#1754670723
rsync -aun --info=NAME,STATS /home/notesao/ffltest/ /home/notesao/lankford/
#1754670841
rsync -au --info=progress2 /home/notesao/ffltest/ /home/notesao/lankford/
#1754670938
diff -qr /home/notesao/lankford/public_html /home/notesao/lankford/"public_html copy"
#1754671039
grep -RinE 'ffl|free for life|free' /home/notesao/lankford/
#1754671055
grep -RinE 'ffl|free for life' /home/notesao/lankford/
#1754671098
grep -RinE 'ffl|free for life' /home/notesao/lankford/ > /home/notesao/lankford_search_results.txt
#1754671275
grep -RinI   --exclude-dir={.git,backups,Backup,save,saves,GeneratedDocuments,public_html/documents,documents/generate_reports}   --exclude='*.{zip,jar,7z,tar,gz,bz2,xz,log,pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,svg,ico,css.map,min.js,min.css}'   -E 'ffl|free for life|(^|[^a-zA-Z])free([^a-zA-Z]|$)'   /home/notesao/lankford/   > /home/notesao/lankford_ffl_filtered.txt
#1754671280
sudo grep -RinI   --exclude-dir={.git,backups,Backup,save,saves,GeneratedDocuments,public_html/documents,documents/generate_reports}   --exclude='*.{zip,jar,7z,tar,gz,bz2,xz,log,pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,svg,ico,css.map,min.js,min.css}'   -E 'ffl|free for life|(^|[^a-zA-Z])free([^a-zA-Z]|$)'   /home/notesao/lankford/   > /home/notesao/lankford_ffl_filtered.txt
#1754672777
grep -RinI 'lankford\.notesao' /home/notesao/lankford/
#1755188151
# See session files and their ages for PHP 8.1 pool
#1755188151
ls -lt /var/cpanel/php/sessions/ea-php81 | head -n 30
#1755188156
sudo ls -lt /var/cpanel/php/sessions/ea-php81 | head -n 30
#1755808507
# create the folder if missing
#1755808507
mkdir -p /home/notesao/sage
#1755808507
# safety: bail out if target already has files
#1755808507
if [ -n "$(ls -A /home/notesao/sage 2>/dev/null)" ]; then   echo "Refusing to copy: /home/notesao/sage is not empty.";   exit 1; fi
#1755808594
ls -la /home/notesao/sage | sed -n '1,80p'
#1755808746
rsync -ain /home/notesao/ffltest/ /home/notesao/sage/
#1755808775
rsync -aHAX --info=progress2 /home/notesao/ffltest/ /home/notesao/sage/
#1755808832
find /home/notesao/ffltest -xdev -printf '%u:%g\n' | sort -u
#1755808838
find /home/notesao/sage -xdev -printf '%u:%g\n' | sort -u
#1755808903
rsync -ain /home/notesao/ffltest/ /home/notesao/sage/
#1755808999
rsync -aHAX --info=progress2 /home/notesao/ffltest/ /home/notesao/saferpath/
#1755872093
mysql -uroot -p -e "SELECT SCHEMA_NAME 
FROM information_schema.SCHEMATA 
WHERE SCHEMA_NAME REGEXP '^(clinicnotepro|notesao)_saferpath$';"
#1755872101
sudo mysql -uroot -p -e "SELECT SCHEMA_NAME 
FROM information_schema.SCHEMATA 
WHERE SCHEMA_NAME REGEXP '^(clinicnotepro|notesao)_saferpath$';"
#1755872124
sudo mysql -uroot -p -e "SELECT SCHEMA_NAME 
FROM information_schema.SCHEMATA 
WHERE SCHEMA_NAME REGEXP '^(clinicnotepro|notesao)_saferpath$';"
#1755872188
sudo mysql -uroot -p -e "
SELECT SCHEMA_NAME
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME LIKE 'clinicnotepro\_%' ESCAPE '\\'
ORDER BY SCHEMA_NAME;"
#1755872251
# List all clinic DBs
#1755872251
sudo mysql -uroot -p -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME REGEXP '^clinicnotepro_';"
#1755872280
sudo mysql -uroot -p -e "SELECT SCHEMA_NAME AS db, DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation FROM information_schema.SCHEMATA WHERE SCHEMA_NAME REGEXP '^clinicnotepro_' ORDER BY db;"
#1755872294
sudo mysql -uroot -p -e "SELECT s.SCHEMA_NAME AS db, IFNULL(GROUP_CONCAT(DISTINCT CASE WHEN sp.GRANTEE='''clinicnotepro''@''localhost''' THEN sp.PRIVILEGE_TYPE END ORDER BY PRIVILEGE_TYPE SEPARATOR ', '), '') AS clinicnotepro_privs FROM information_schema.SCHEMATA s LEFT JOIN information_schema.SCHEMA_PRIVILEGES sp ON sp.TABLE_SCHEMA=s.SCHEMA_NAME WHERE s.SCHEMA_NAME REGEXP '^clinicnotepro_' GROUP BY s.SCHEMA_NAME ORDER BY s.SCHEMA_NAME;"
#1755872567
sudo mysql -uroot -p -e "
SELECT COUNT(*) AS db_privs
FROM information_schema.SCHEMA_PRIVILEGES
WHERE TABLE_SCHEMA='clinicnotepro_saferpath'
  AND GRANTEE='''clinicnotepro''@''localhost''';"
#1755872585
sudo mysql -uroot -p -e "
SELECT COUNT(*) AS tbl_privs
FROM information_schema.TABLE_PRIVILEGES
WHERE TABLE_SCHEMA='clinicnotepro_saferpath'
  AND GRANTEE='''clinicnotepro''@''localhost''';"
#1755872595
sudo mysql -uroot -p -e "
GRANT ALL PRIVILEGES ON clinicnotepro_saferpath.* TO 'clinicnotepro'@'localhost';
FLUSH PRIVILEGES;"
#1755872608
sudo mysql -uroot -p -e "SHOW GRANTS FOR 'clinicnotepro'@'localhost';"
#1755876079
cd /home/notesao/saferpath
#1755876079
# Files (unique) that have at least one match
#1755876079
grep -RIl   --include='*.php' --include='*.sh'   --exclude='*.bak' --exclude='*.log' --exclude='*.bak.*' --exclude='*.log.*'   --exclude-dir={.git,node_modules,vendor,storage,logs,backup,backups,tmp,temp}   -E -i 'ffl\b|free[ _-]?for[ _-]?life' .
#1755876379
cd /home/notesao/saferpath
#1755876379
grep -RInI   --exclude='*.bak' --exclude='*.bak.*'   --exclude='*.log' --exclude='*.log.*'   --exclude-dir={.git,node_modules,vendor,storage,logs,backup,backups,tmp,temp}   -E -i 'ffl|free[ _-]?for[ _-]?life' .
#1755876569
grep -RInI   --exclude='*.bak' --exclude='*.bak.*'   --exclude='*.log' --exclude='*.log.*'   --exclude='error_log'   --exclude='favicon.svg'   --exclude-dir={.git,node_modules,vendor,storage,logs,backup,backups,tmp,temp}   -E -i 'ffl|free[ _-]?for[ _-]?life' .
#1755879835
cd /home/notesao/sage
#1755879841
grep -RInI   --exclude='*.bak' --exclude='*.bak.*'   --exclude='*.log' --exclude='*.log.*'   --exclude='error_log'   --exclude='favicon.svg'   --exclude-dir={.git,node_modules,vendor,storage,logs,backup,backups,tmp,temp}   -E -i 'ffl|free[ _-]?for[ _-]?life' .
#1755889670
sudo ls -1 /usr/local/apache/domlogs/ | sed -n '1,200p'
#1755889973
# show the code around the failing line
#1755889973
nl -ba /home/notesao/ffltest/public_html/check_in_step3.php | sed -n '80,120p'
#1755890224
export D='22/Aug/2025'   # adjust day
#1755890224
sudo awk -v d="$D" '$0 ~ d && /check_in_step3\.php/'   /usr/local/apache/domlogs/ffltest.notesao.com* 2>/dev/null | tail -n 80
#1755894629
sudo grep -RIn "disable_functions" /opt/cpanel/ea-php*/root/etc/php-fpm.d/*sandbox*.conf || true
#1755894641
sudo grep -RIn "disable_functions" /opt/cpanel/ea-php*/root/etc/php.ini /opt/cpanel/ea-php*/root/etc/conf.d/*.ini || true
#1755894711
# From "this general location" (the directory you're in):
#1755894711
sudo grep -RInE '^\s*(php_admin_value\[disable_functions\]|disable_functions)\s*=\s*.*\bexec\b' .
#1755894727
sudo grep -RInE --include="*.conf" --include="*.ini" --include=".user.ini"   '^\s*(php_admin_value\[disable_functions\]|disable_functions)\s*=\s*.*\bexec\b'   /opt/cpanel/ea-php*/root/etc/ 2>/dev/null
#1755894790
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf
#1755894810
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/sandbox.clinic.notepro.co.conf
#1755894846
# Preferred: reload both PHP-FPM services
#1755894846
sudo systemctl reload ea-php81-php-fpm ea-php82-php-fpm
#1756312130
# From "this general location" (the directory you're in):
#1756312130
sudo grep -RInE '^\s*(php_admin_value\[disable_functions\]|disable_functions)\s*=\s*.*\bexec\b' .
#1756312189
sudo grep -RIn "disable_functions" /opt/cpanel/ea-php*/root/etc/php.ini /opt/cpanel/ea-php*/root/etc/conf.d/*.ini || true
#1756312266
# List every PHP-FPM pool conf (all PHP versions) that still disables exec or shell_exec
#1756312266
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*.*\b(exec|shell_exec)\b'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/*.conf 2>/dev/null
#1756312311
# Show every pool file (all PHP versions) that still lists exec or shell_exec
#1756312311
sudo grep -RInE '^\s*(php_admin_value\[disable_functions\]|disable_functions)\s*=\s*[^#\n]*(\bexec\b|\bshell_exec\b)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/*.conf 2>/dev/null
#1756312320
# Global php.ini and conf.d snippets across PHP versions
#1756312320
sudo grep -RInE '^\s*disable_functions\s*=\s*[^#\n]*(\bexec\b|\bshell_exec\b)'   /opt/cpanel/ea-php*/root/etc/php.ini /opt/cpanel/ea-php*/root/etc/conf.d/*.ini 2>/dev/null
#1756312428
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1756312428
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1756312563
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf
#1756312583
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf
#1756312610
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.notesao.com.conf
#1756312644
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notepro.co.conf
#1756312662
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf
#1756312680
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf
#1756312698
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf
#1756312727
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf
#1756312745
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf
#1756312765
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf
#1756312784
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf
#1756312802
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf
#1756312822
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/clinic.notepro.co.conf
#1756312867
# Reload (preferred – no downtime)
#1756312867
sudo systemctl reload ea-php81-php-fpm ea-php82-php-fpm
#1756479040
chmod 750 /home/notesao/secure
#1756479053
chmod 640 /home/notesao/secure/notesao_secrets.php
#1756479069
chown notesao:notesao /home/notesao/secure/notesao_secrets.php
#1756483573
ln -s /home/notesao/public_html/favicons /home/notesao/enroll/public_html/favicons
#1756483577
ln -s /home/notesao/public_html/assets   /home/notesao/enroll/public_html/assets
#1756485290
cd /home/notesao/enroll/public_html
#1756485297
composer require dompdf/dompdf
#1756488388
chmod 644 /home/notesao/enroll/public_html/print_eua.php
#1756488393
chmod 644 /home/notesao/enroll/public_html/_agreements/pdf/eua.pdf
#1756488405
sudo chmod 644 /home/notesao/enroll/public_html/print_eua.php
#1756752703
sudo mysql -e "SELECT user,host,plugin FROM mysql.user ORDER BY user,host;"
#1756752733
sudo mysql -e "
SELECT grantee, privilege_type, table_schema
FROM information_schema.SCHEMA_PRIVILEGES
WHERE table_schema IN ('clinicnotepro_ffltest','clinicnotepro_template')
ORDER BY table_schema, grantee, privilege_type;"
#1756752758
sudo mysql -e "
SELECT grantee, privilege_type, table_schema, table_name
FROM information_schema.TABLE_PRIVILEGES
WHERE table_schema IN ('clinicnotepro_ffltest','clinicnotepro_template')
ORDER BY table_schema, table_name, grantee;"
#1756752771
sudo mysql -e "
SELECT table_schema, table_name, DEFINER
FROM information_schema.VIEWS
WHERE table_schema IN ('clinicnotepro_ffltest','clinicnotepro_template')
ORDER BY table_schema, table_name;"
#1756752844
sudo mysql -e "SELECT grantee, privilege_type, table_schema
FROM information_schema.SCHEMA_PRIVILEGES
WHERE table_schema IN ('clinicnotepro_ffltest','clinicnotepro_template')
ORDER BY table_schema, grantee, privilege_type;"
#1756752844
sudo mysql -e "SELECT grantee, privilege_type, table_schema, table_name
FROM information_schema.TABLE_PRIVILEGES
WHERE table_schema IN ('clinicnotepro_ffltest','clinicnotepro_template')
ORDER BY table_schema, table_name, grantee;"
#1756752844
sudo mysql -e "SELECT table_schema, table_name, DEFINER
FROM information_schema.VIEWS
WHERE table_schema IN ('clinicnotepro_ffltest','clinicnotepro_template')
ORDER BY table_schema, table_name;"
#1756752968
# grant the template app user
#1756752968
sudo mysql -e "
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'localhost';
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'host.ekwk7v-lwsites.com';
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'host.notesao.com';
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'50.28.37.79';
FLUSH PRIVILEGES;"
#1756753058
sudo mysql -e "
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'localhost';
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'host.ekwk7v-lwsites.com';
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'host.notesao.com';
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'50.28.37.79';
FLUSH PRIVILEGES;"
#1756753076
# See which host identities exist for the user
#1756753076
sudo mysql -e "SELECT user,host FROM mysql.user WHERE user='clinicnotepro_template_app' ORDER BY host;"
#1756753076
# Confirm schema-level grants
#1756753076
sudo mysql -e "
SELECT grantee, privilege_type
FROM information_schema.SCHEMA_PRIVILEGES
WHERE table_schema='clinicnotepro_template'
ORDER BY grantee, privilege_type;"
#1756753076
# Optional live test (will prompt for password)
#1756753076
mysql -u clinicnotepro_template_app -p -h localhost -e "USE clinicnotepro_template; SHOW TABLES;"
#1756753409
( export MYSQL_PWD='6Ydlwg90Tb-wt7'
mysql -uroot -h localhost --protocol=TCP <<'SQL'
-- Ensure the database exists
CREATE DATABASE IF NOT EXISTS clinicnotepro_template
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

-- Give the phpMyAdmin MySQL user full rights so it appears in the left pane
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro'@'localhost';

-- (Optional) also grant to the template app user if you’ll use it
GRANT ALL PRIVILEGES ON clinicnotepro_template.* TO 'clinicnotepro_template_app'@'localhost';

FLUSH PRIVILEGES;

-- Quick checks
SELECT SCHEMA_NAME FROM information_schema.SCHEMATA
 WHERE SCHEMA_NAME='clinicnotepro_template';

SELECT grantee, privilege_type
  FROM information_schema.SCHEMA_PRIVILEGES
 WHERE table_schema='clinicnotepro_template'
   AND grantee IN ('''clinicnotepro''@''localhost''','''clinicnotepro_template_app''@''localhost''')
 ORDER BY grantee, privilege_type;
SQL
 )
#1756846373
grep -RIn "consent_p1_" /home/notesao/safatherhood/public_html/
#1756846379
grep -RInE "consent_p1_signature|consent_p1_date" /home/notesao/safatherhood/public_html/
#1756915666
rsync -aH --delete --dry-run /home/notesao/ffltest/ /home/notesao/lakeview/
#1756915703
rsync -aH /home/notesao/ffltest/ /home/notesao/lakeview/
#1756915874
grep -RInI -i -E '(ffl|free[[:space:]_-]*for[[:space:]_-]*life|(^|[^[:alpha:]])van([^[:alpha:]]|$)|5102)' /home/notesao/lakeview/ 2>/dev/null
#1756915950
grep -RInI --exclude='*.log' -i -E '(ffl|free[[:space:]_-]*for[[:space:]_-]*life|(^|[^[:alpha:]])van([^[:alpha:]]|$)|5102)' /home/notesao/lakeview/ 2>/dev/null
#1756916002
grep -RInI --exclude='*.log' -i -E '(ffl|free[[:space:]_-]*for[[:space:]_-]*life|(^|[^[:alpha:]])van([^[:alpha:]]|$)|5102)' /home/notesao/lakeview/ 2>/dev/null > /home/notesao/search_audit.txt
#1756918142
mysql -u root -p
#1756998930
# simple (recursive, case-sensitive, show file:line)
#1756998930
grep -RInF 'BIPP (male)' /home/notesao/lakeview 2>/dev/null
#1756999572
grep -RInF --binary-files=without-match   'clinicnotepro_ffltest' /home/notesao/lakeview 2>/dev/null
#1757343441
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757343441
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757343528
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf
#1757343547
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/sandbox.clinic.notepro.co.conf
#1757343571
# Reload (preferred – no downtime)
#1757343571
sudo systemctl reload ea-php81-php-fpm ea-php82-php-fpm
#1757600098
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757600098
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757600252
[sudo] password for notesao:
#1757600252
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600252
/opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600252
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600252
/opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600252
/opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.notesao.com.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600252
/opt/cpanel/ea-php81/root/etc/php-fpm.d/notepro.co.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600252
/opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.bak-2025-06-17:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.notesao.com.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.notesao.com.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php82/root/etc/php-fpm.d/clinic.notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php82/root/etc/php-fpm.d/sandbox.clinic.notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php82/root/etc/php-fpm.d/sandbox.clinic.notepro.co.conf.save:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php82/root/etc/php-fpm.d/clinic.notepro.co.conf:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600253
/opt/cpanel/ea-php82/root/etc/php-fpm.d/clinic.notepro.co.conf.bak-202505051537:18:php_admin_value[disable_functions] = exec,passthru,shell_exec,system
#1757600300
[notesao@host ~]$sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf.save
#1757600308
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf.save
#1757600327
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf
#1757600359
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf
#1757600382
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.notesao.com.conf
#1757600401
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notepro.co.conf
#1757600438
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf.bak-202505051537
#1757600455
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf.bak-202505051537
#1757600473
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf.save
#1757600488
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notepro.co.conf.bak-202505051537
#1757600505
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf.bak-202505051537
#1757600545
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757600545
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757600561
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf.save
#1757600583
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf.bak-202505051537
#1757600604
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf.bak-202505051537
#1757600625
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf.save
#1757600744
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf.save
#1757600765
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf.bak-202505051537
#1757600786
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)' >   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757600799
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757600799
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757600808
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf
#1757600831
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.bak-2025-06-17:18
#1757600847
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf
#1757600865
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757600865
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757600898
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.bak-2025-06-17:18
#1757600910
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf
#1757600930
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notepro.co.conf.save
#1757600954
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf.bak-202505051537
#1757600973
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf.save
#1757600993
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf.bak-202505051537
#1757601010
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757601010
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757601149
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/clinic.notepro.co.conf.bak-202505051537
#1757601173
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/clinic.notepro.co.conf
#1757601192
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/sandbox.clinic.notepro.co.conf.save
#1757601214
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/sandbox.clinic.notepro.co.conf.bak-202505051537
#1757601237
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf
#1757601265
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757601265
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757601286
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.notesao.com.conf.save
#1757601308
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf.save
#1757601328
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf.bak-202505051537
#1757601348
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.bak-202505051537
#1757601369
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf.save
#1757601397
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.save
#1757601414
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757601414
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757601434
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf
#1757601454
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.notesao.com.conf.save
#1757601479
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.notesao.com.conf.bak-202505051537
#1757601501
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.clinic.notepro.co.conf.bak-202505051537
#1757601525
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.clinic.notepro.co.conf
#1757601541
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757601541
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757601559
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/safatherhood.notesao.com.conf
#1757601581
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ffltest.clinic.notepro.co.conf
#1757601601
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/denton.clinic.notepro.co.conf
#1757601621
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/transform.notesao.com.conf.bak-202505051537
#1757601637
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757601637
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757601653
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf.save
#1757601671
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/dwag.clinic.notepro.co.conf.save
#1757601691
sudo nano /opt/cpanel/ea-php82/root/etc/php-fpm.d/clinic.notepro.co.conf.save
#1757601709
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757601709
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757601727
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/sandbox.notesao.com.conf.bak-2025-06-17
#1757601743
# PHP-FPM pool files for ALL PHP versions (includes .conf.bak, .rpmsave, etc.)
#1757601743
sudo grep -RInE '^\s*php_admin_value\[disable_functions\]\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/php-fpm.d/ 2>/dev/null
#1757601750
# Global php.ini + conf.d across versions (and their backups)
#1757601750
sudo grep -RInE '^\s*disable_functions\s*=\s*[^#\n]*(exec|shell_exec)'   /opt/cpanel/ea-php*/root/etc/ 2>/dev/null
#1757601754
# Any per-site overrides under your account (.user.ini / php.ini), incl. backups
#1757601754
sudo grep -RInE --include=".user.ini*" --include="php.ini*"   '^\s*disable_functions\s*=\s*[^#\n]*(exec|shell_exec)'   /home/notesao 2>/dev/null
#1757601886
sudo systemctl reload ea-php81-php-fpm ea-php82-php-fpm
#1757602750
curl -sI -H 'Host: notesao.com' http://127.0.0.1/login.php | head -n1
#1757603554
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf
#1757603615
curl -skI --resolve notesao.com:443:127.0.0.1 https://notesao.com/login.php | head -n1
#1757603628
curl -sI -H 'Host: notesao.com' http://127.0.0.1/login.php | head -n1
#1757603654
ls -lah /home/notesao/public_html/login.php
#1757603659
stat /home/notesao/public_html /home/notesao/public_html/login.php
#1757603668
tail -n 200 /usr/local/apache/logs/error_log   | egrep -i 'fcgi|proxy:fcgi|primary script unknown|AH0107|AH01071|AH01079|AH01014'
#1757603675
tail -n 200 /opt/cpanel/ea-php81/root/var/log/php-fpm/error.log
#1757603682
sudo tail -n 200 /opt/cpanel/ea-php81/root/var/log/php-fpm/error.log
#1757603686
tail -n 200 /opt/cpanel/ea-php82/root/var/log/php-fpm/error.log
#1757603691
sudo tail -n 200 /opt/cpanel/ea-php82/root/var/log/php-fpm/error.log
#1757603963
sudo systemctl reload ea-php81-php-fpm ea-php82-php-fpm
#1757603991
chmod 755 /home/notesao/public_html
#1757603995
chmod 644 /home/notesao/public_html/login.php
#1757603999
httpd -S | sed -n '/notesao\.com/Ip'
#1757604003
sudo httpd -S | sed -n '/notesao\.com/Ip'
#1757604143
sudo sed -n '560,660p' /etc/apache2/conf/httpd.conf
#1757604153
sudo sed -n '2950,3060p' /etc/apache2/conf/httpd.conf
#1757604344
cat >/home/notesao/public_html/phpinfo.php <<'PHP'
<?php phpinfo();
PHP

#1757604344
# Test HTTPS vhost directly
#1757604344
curl -skI --resolve notesao.com:443:127.0.0.1 https://notesao.com/phpinfo.php | head -n1
#1757604369
sudo tail -n 100 /usr/local/apache/logs/error_log
#1757604584
sudo /opt/cpanel/ea-php81/root/usr/sbin/php-fpm -t
#1757604713
sudo nl -ba /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf | sed -n '1,60p'
#1757604878
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.clinic.notepro.co.conf
#1757604914
sudo /opt/cpanel/ea-php81/root/usr/sbin/php-fpm -t
#1757604921
sudo systemctl restart ea-php81-php-fpm
#1757604994
ls -l /opt/cpanel/ea-php81/root/usr/var/run/php-fpm/18b6f2569a070883f219fc9c0c1c68cf5f214b95.sock
#1757605000
curl -skI --resolve notesao.com:443:127.0.0.1 https://notesao.com/phpinfo.php | head -n1
#1757605006
curl -skI --resolve notesao.com:443:127.0.0.1 https://notesao.com/login.php   | head -n1
#1757605410
sudo grep -RIn 'php_admin_value\[disable_functions\]' /opt/cpanel/ea-php*/root/etc/php-fpm.d/
#1757605466
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/notesao.com.conf
#1757605491
sudo sed -n '1,200p' /var/cpanel/ApachePHPFPM/system_pool_defaults.yaml
#1757610547
sudo nano /opt/cpanel/ea-php81/root/etc/php-fpm.d/ctc.notesao.com.conf.save
#1757963799
DRY_RUN=1 php -r '
  $_SERVER["REQUEST_METHOD"]="POST";
  $_POST = ["action"=>"quick","gid"=>"104","csrf"=>"cli"];
  include "client-reminders.php";
'
#1757963942
DRY_RUN=1 php -d sendmail_path=/bin/true -r '$_SERVER["REQUEST_METHOD"]="POST"; $_POST=["action"=>"quick","gid"=>"104","csrf"=>"cli"]; include "/home/notesao/public_html/client-reminders.php";'
#1757964150
DRY_RUN=1 php -d sendmail_path=/bin/true -r '$_SERVER["REQUEST_METHOD"]="POST"; $_POST=["action"=>"quick","gid"=>"104","csrf"=>"cli","dry_run"=>"1"]; include "/home/notesao/ffltest/public_html/client-reminders.php";'
#1757964369
hp -d display_errors=1 -d error_reporting=E_ALL -d sendmail_path=/bin/cat -r 'chdir("/home/notesao/ffltest/public_html"); session_id("cli"); session_start(); $_SESSION["csrf"]="cli"; $_SESSION["loggedin"]=1; $_SESSION["user_id"]=1; $_SERVER["REQUEST_METHOD"]="POST"; $_POST=["action"=>"quick","gid"=>"104","csrf"=>"cli"]; include "client-reminders.php";'
#1757964406
php -d display_errors=1 -d error_reporting=E_ALL -d sendmail_path=/bin/cat -r 'chdir("/home/notesao/ffltest/public_html"); session_id("cli"); session_start(); $_SESSION["csrf"]="cli"; $_SESSION["loggedin"]=1; $_SESSION["user_id"]=1; $_SERVER["REQUEST_METHOD"]="POST"; $_POST=["action"=>"quick","gid"=>"104","csrf"=>"cli"]; include "client-reminders.php";'
#1757964555
php -d display_errors=1 -d error_reporting=E_ALL -d sendmail_path=/bin/cat -r 'chdir("/home/notesao/ffltest/public_html"); require "auth.php"; session_id("cli"); session_start(); $_SESSION["csrf"]="cli"; $_SESSION["loggedin"]=1; $_SESSION["user_id"]=1; $_SERVER["REQUEST_METHOD"]="POST"; $_POST=["action"=>"quick","gid"=>"104","csrf"=>"cli"]; include "client-reminders.php";'
#1757966737
php -d display_errors=1 -d error_reporting=E_ALL -d sendmail_path=/bin/cat -r 'chdir("/home/notesao/ffltest/public_html"); require "auth.php"; session_id("cli"); session_start(); $_SESSION["csrf"]="cli"; $_SESSION["loggedin"]=1; $_SESSION["user_id"]=1; $_SERVER["REQUEST_METHOD"]="POST"; $_POST=["action"=>"quick","gid"=>"105","csrf"=>"cli"]; include "client-reminders.php";'
#1757967314
php -d display_errors=1 -d error_reporting=E_ALL -d sendmail_path=/bin/cat -r '
  chdir("/home/notesao/ffltest/public_html");
  require "auth.php";                           // initialize $con
  session_id("cli"); session_start();
  $_SESSION["csrf"]="cli"; $_SESSION["loggedin"]=1; $_SESSION["user_id"]=1;
  $_SERVER["REQUEST_METHOD"]="POST";
  $_POST = ["action"=>"quick","gid"=>"t4c:monday","csrf"=>"cli"];
  include "client-reminders.php";
'
#1757968693
---------------------------------------------------------------------------------------------------
#1757968703
php -d display_errors=1 -d error_reporting=E_ALL -d sendmail_path=/bin/cat -r '
  chdir("/home/notesao/ffltest/public_html");
  require "auth.php";                           // initialize $con
  session_id("cli"); session_start();
  $_SESSION["csrf"]="cli"; $_SESSION["loggedin"]=1; $_SESSION["user_id"]=1;
  $_SERVER["REQUEST_METHOD"]="POST";
  $_POST = ["action"=>"quick","gid"=>"t4c:monday","csrf"=>"cli"];
  include "client-reminders.php";
'
#1757970585
mkdir -p /home/notesao/mail-logs && chmod 700 /home/notesao/mail-logs
#1757970595
find /home/notesao/mail-logs -type f -name 'reminders-*.log' -mtime +30 -delete
#1758572180
cd /home/notesao/NotePro-Report-Generator
#1758572180
find . -print | sed 's|^\./||' | sort > inventory.txt
#1758572550
grep -RIn --binary-files=without-match --color   -E '/home/notesao/NotePro-Report-Generator(/|$)|(^|[^A-Za-z0-9_-])NotePro-Report-Generator(/|$)'   --exclude-dir=NotePro-Report-Generator   /home/notesao 2>/dev/null
#1758572850
-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
#1758572853
grep -RIn --binary-files=without-match --color   -E '/home/notesao/NotePro-Report-Generator(/|/[^[:space:]]+)'   --include='*.php' --include='*.sh'   --exclude-dir='NotePro-Report-Generator'   --exclude-dir='.vscode-server' --exclude-dir='.git' --exclude-dir='node_modules'   /home/notesao 2>/dev/null | sort -u
#1759349700
grep -RIl --include='*.php' -E '(include|require)(_once)?\s*\(?\s*["'\''"][^"'\''"]*navbar\.php["'\''"]'   /home/notesao/safatherhood/public_html | xargs -I{} sh -c 'grep -qiE "(font-?awesome|use\.fontawesome\.com|cdnjs\.cloudflare\.com/.*/font-?awesome)" "{}" || echo "{}"'
#1759771857
# Compare BestOption clinic app to a known-good clinic (ffltest) without copying
#1759771857
diff -qr /home/notesao/ffltest/public_html /home/notesao/bestoption/public_html | tee ~/bestoption_public_diff.txt
#1759771857
# List BestOption templates actually present
#1759771857
find /home/notesao/NotePro-Report-Generator/templates/bestoption -type f | sort
#1759771857
# Detect cross-clinic names inside BestOption templates
#1759771857
grep -RniE "DWAG|Fatherhood|AIT-SCM|San Antonio Fatherhood"   /home/notesao/NotePro-Report-Generator/templates/bestoption | tee ~/bestoption_brand_issues.txt
#1759771989
diff -qr /home/notesao/safatherhood/public_html /home/notesao/bestoption/public_html | tee ~/bestoption_saf_public_diff.txt
#1759957930
tail -n 200 /home/notesao/NotePro-Report-Generator/fetch_data_errors.log
#1760019339
tmp=$(mktemp) && tail -n 1000 "/home/notesao/NotePro-Report-Generator/fetch_data_errors.log" > "$tmp" && mv "$tmp" "/home/notesao/NotePro-Report-Generator/fetch_data_errors.log"
#1760386145
diff -u --color=always   /home/notesao/dwag/public_html/intake-review.php   /home/notesao/safatherhood/public_html/intake-review.php | less -R
#1760369466
cd /home/notesao
#1760369466
comm -23 <(find ffltest -type f -printf '%P\n' | sort) <(find dwag -type f -printf '%P\n' | sort)
#1760370743
cd /home/notesao
#1760370743
comm -13 <(find ffltest -type f -printf '%P\n' | sort) <(find dwag -type f -printf '%P\n' | sort)
#1760386100
git --no-pager diff --no-index --word-diff=color   --word-diff-regex='[^[:space:]]+'   /home/notesao/dwag/public_html/intake-review.php   /home/notesao/safatherhood/public_html/intake-review.php | less -R
#1760386179
sdiff -w 180 --suppress-common-lines   /home/notesao/dwag/public_html/intake-review.php   /home/notesao/safatherhood/public_html/intake-review.php
