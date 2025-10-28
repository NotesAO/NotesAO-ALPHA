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
#1760559362
sudo du -sh /home/notesao
#1760558777
# 1) confirm tar still running
#1760558777
pgrep -u notesao -fl tar
#1760558777
# 2) watch archive growth (stop anytime with Ctrl+C)
#1760558777
f=$(ls -1t /home/notesao/backups/pre-git-clean-fixed-*.tgz | head -n1)
#1760558777
while sleep 2; do ls -lh "$f"; done
#1760559896
pgrep -u notesao -fl tar || echo "no tar process"
#1760559896
ls -lh /home/notesao/backups/pre-git-clean-fixed-20251015-145620.tgz
#1760559896
tar -tzf /home/notesao/backups/pre-git-clean-fixed-20251015-145620.tgz | head -n 10
#1760559896
tar -tzf /home/notesao/backups/pre-git-clean-fixed-20251015-145620.tgz >/dev/null
#1760559949
pgrep -u notesao -fl tar || echo "no tar process"
#1760559972
# integrity check
#1760559972
tar -tzf /home/notesao/backups/pre-git-clean-fixed-20251015-145620.tgz >/dev/null
#1760560080
tar -tzf /home/notesao/backups/pre-git-clean-fixed-20251015-145620.tgz >/dev/null && echo "archive OK"
#1760560443
find /home/notesao -type d -name .git -prune -print | sort
#1760560451
find /home/notesao -type f -name .git -print | sort
#1760560458
find /home/notesao -maxdepth 3 -type f \( -name .gitmodules -o -name .gitattributes \) -print | sort
#1760560507
ts=$(date +"%Y%m%d-%H%M%S")
#1760560507
find /home/notesao -type d -name .git -prune -print | sort   | tee /home/notesao/backups/git-metadata-$ts.txt
#1760560903
find /home/notesao -type d -name .git -prune -exec rm -rf {} +
#1760560914
rm -f /home/notesao/Downloads/generator/.gitattributes       /home/notesao/Downloads/.gitattributes       /home/notesao/NotePro-Report-Generator/.gitattributes
#1760560920
find /home/notesao -name .git
#1760560920
git -C /home/notesao/NotePro-Report-Generator status 2>&1 | head -n1
#1760560920
ls -la /home/notesao/NotePro-Report-Generator | head
#1760561537
ssh -T git@github.com
#1760561739
mkdir -p ~/.ssh
#1760561739
chmod 700 ~/.ssh
#1760561739
chmod 600 ~/.ssh/id_rsa_github
#1760561739
chmod 644 ~/.ssh/id_rsa_github.pub
#1760561739
cat > ~/.ssh/config <<'EOF'
Host github.com
  HostName ssh.github.com
  User git
  Port 443
  IdentityFile /home/notesao/.ssh/id_rsa_github
  IdentitiesOnly yes
EOF

#1760561739
chmod 600 ~/.ssh/config
#1760561748
ssh -T git@github.com
#1760562099
cd /home/notesao
#1760562099
git init
#1760562099
git branch -M main
#1760562099
git remote add origin git@github.com:NotesAO/NotesAO-ALPHA.git
#1760562099
git add -A
#1760562177
cat > /home/notesao/.gitignore <<'EOF'
# secrets
config/config.php
.env
.env.*

# backups and archives
backups/
*.tgz
*.tar
*.tar.gz
*.zip
*.7z

# outputs
*.log
logs/
log/
tmp/
cache/
GeneratedDocuments/
Generated_Reports*/
NotePro-Report-Generator/csv/
public_html/*/uploads/

# binaries you don’t want in git
*.pdf
*.docx
*.odt
*.xlsx
*.xls

# vendors
node_modules/
vendor/

# python
__pycache__/
*.pyc
venv*/
.pytest_cache/

# IDE
.idea/
.vscode/

# misc
*_bak.php
*.orig
~$*.*
.~lock*.#
EOF

#1760562181
cd /home/notesao
#1760562181
git rm -r --cached . 2>/dev/null || true
#1760562181
git add -A
#1760562336
git commit -m "initial commit from live server (with .gitignore)"
#1760562360
git config --global user.name "NotesAO Server"
#1760562360
git config --global user.email "admin@notesao.com"
#1760562366
git commit -m "initial commit from live server (with .gitignore)"
#1760562381
git push -u origin main
#1760562563
cd /home/notesao
#1760562563
cat >> .gitignore <<'EOF'
.vscode-server/
Downloads/
patch_work/
EOF

#1760562567
git rm -r --cached .vscode-server Downloads patch_work
#1760562577
git ls-files -z | xargs -0 -I{} bash -lc 'f="{}"; [ -f "$f" ] && [ $(stat -c%s "$f") -gt $((90*1024*1024)) ] && echo "$f"'
#1760562659
git commit --amend -m "initial commit from live server (pruned large binaries and downloads)"
#1760562664
git push -u origin main
#1760562735
cd /home/notesao
#1760562735
cat >> .gitignore <<'EOF'
# NPRG installers and bundles
NotePro-Report-Generator/LibreOffice_*/
NotePro-Report-Generator/**/*.rpm
NotePro-Report-Generator/**/*.deb
NotePro-Report-Generator/**/*.pkg
NotePro-Report-Generator/**/*.tar
NotePro-Report-Generator/**/*.tar.*
NotePro-Report-Generator/**/*.tgz
NotePro-Report-Generator/**/*.zip
EOF

#1760562740
git rm -r --cached --   "NotePro-Report-Generator/LibreOffice_24.8.2_Linux_x86-64_rpm.tar.gz.1"   "NotePro-Report-Generator/LibreOffice_24.8.2.1_Linux_x86-64_rpm" 2>/dev/null || true
#1760562746
git commit --amend -m "initial commit (prune large installers and bundles)"
#1760562750
git push -u origin main
#1760563253
cd /home/notesao
#1760563253
git add -A
#1760563253
git commit -m "baseline after initial import"
#1760563253
git push
#1760640179
# Fast (if ripgrep is installed)
#1760640179
rg -n --hidden -S -g '!vendor' -g '!node_modules' 'absence_logic\.php' /home/notesao
#1760640179
# Portable grep version
#1760640179
grep -RIn --include='*.{php,js,ts,html}' --exclude-dir={vendor,node_modules,.git}   -E 'absence_logic\.php|/absence_logic\.php' /home/notesao 2>/dev/null
#1760640179
# Narrow to include/require calls
#1760640179
grep -RIn --include='*.php' --exclude-dir={vendor,node_modules,.git}   -E '(include|require)(_once)?\s*\(?["'\''].*absence_logic\.php' /home/notesao
#1760641539
php -l /home/notesao/dwag/public_html/absence_logic.php
#1760641643
bash -x /home/notesao/dwag/scripts/create_absence.sh
#1760641878
cd /home/notesao/dwag/scripts/logs
#1760641878
ls -lt absence_*.html | head
#1760641878
tail -n 120 $(ls -t absence_*.html | head -1) | sed -n 's/.*\(Checking attendance\|using \|not creating absence\|insertAbsenceRecord\|ERROR\).*/\0/p'
#1760642062
bash -x /home/notesao/dwag/scripts/create_absence.sh
#1760642062
log=$(ls -t /home/notesao/dwag/scripts/logs/absence_*.html | head -1)
#1760642062
grep -E "Checking attendance|start_date|insertAbsenceRecord|awaiting|ERROR|Fatal" "$log" | tail -200
#1760642179
bash -x /home/notesao/dwag/scripts/create_absence.sh
#1760642179
log=$(ls -t /home/notesao/dwag/scripts/logs/absence_*.html | head -1)
#1760642179
grep -E "Checking attendance|start_date|insertAbsenceRecord|awaiting|ERROR|Fatal" "$log" | tail -200
#1760642729
bash -x /home/notesao/dwag/scripts/create_absence.sh
#1760642729
log=$(ls -t /home/notesao/dwag/scripts/logs/absence_*.html | head -1)
#1760642729
grep -E "double attendance|excused prior absence|insertAbsenceRecord|client 11" "$log" | tail -80
#1760642899
bash -x /home/notesao/dwag/scripts/create_absence.sh
#1760642899
log=$(ls -t /home/notesao/dwag/scripts/logs/absence_*.html | head -1)
#1760642899
grep -E "client 11|excused prior absence|insertAbsenceRecord|awaiting|ERROR|Fatal" "$log" | tail -120
#1760644460
bash -x /home/notesao/dwag/scripts/create_absence.sh
#1760644460
log=$(ls -t /home/notesao/dwag/scripts/logs/absence_*.html | head -1)
#1760644460
grep -E "Checking attendance|insertAbsenceRecord|awaiting|ERROR|Fatal" "$log" | tail -200
#1760644665
bash -x /home/notesao/dwag/scripts/create_absence.sh
#1760645058
cd /home/notesao/
#1760645084
cd /home/notesao && out="notesao_tree_$(date +%Y%m%d).txt" && {   echo "== FILE TREE (excluding individual .log* files) ==";   tree -a -F --prune -I '*.log|*.log.*|*.gz|*.bz2|*.xz';   echo; echo "== LOG FILE INDEX (directory → count of logs) ==";   find . -type f \( -name '*.log' -o -name '*.log.*' -o -name '*.gz' -o -name '*.bz2' -o -name '*.xz' \)     -printf '%h\n' | sort | uniq -c | sort -nr;   echo; echo "== DOCX FILES ==";   find . -type f \( -iname '*.docx' -o -iname '*.doc' \) -print | sort; } | tee "$out"
#1760645547
cd /home/notesao && out="notesao_tree_$(date +%Y%m%d).txt" && IGNORE='*.log|*.log.*|*.gz|*.bz2|*.xz|*.zip|*.tar|*.tar.gz|*.tgz|*.rpm|AbsenceReport_*.pdf|absence_date_time_*.html|completion_date_time*.html|node_modules|vendor|.git|.vscode|__pycache__|.venv|venv|build|dist|.cache|.npm|.local|.cpanel|.softaculous|.spamassassin|.ssh|.trash|.caldav|http-v2|new_venv|LibreOffice_*' && {   echo "== FILE TREE (filtered) ==";   tree -a -F --prune -I "$IGNORE";   echo; echo "== LOG FILE INDEX (directory → count) ==";   find . -type f \( -name '*.log' -o -name '*.log.*' -o -name 'absence_date_time_*.html' -o -name 'completion_date_time*.html' \)     -printf '%h\n' | sort | uniq -c | sort -nr;   echo; echo "== DOCX FILES ==";   find . -type f \( -iname '*.docx' -o -iname '*.doc' \) -print | sort; } | tee "$out"
#1760645764
cd /home/notesao && out="notesao_tree_$(date +%Y%m%d).txt" && IGNORE='
*.log|*.log.*|*.gz|*.bz2|*.xz|*.zip|*.7z|*.rar|*.tar|*.tar.*|*.tgz|*.txz|
*.bak|*.old|*.tmp|*.swp|*.iso|*.deb|*.rpm|*.whl|
AbsenceReport_*.pdf|absence_date_time*.pdf|absence_date_time*.html|completion_*.html|
node_modules|vendor|__pycache__|.venv|venv|venv311|new_venv|build|dist|
.git|.vscode|.idea|.pytest_cache|.ruff_cache|.mypy_cache|.tox|coverage|
.cache|.npm|.local|.cpanel|.softaculous|.spamassassin|.ssh|.trash|.caldav|.dotnet|
http-v2|LibreOffice_*|.config/libreoffice
' && {   echo "== FILE TREE (filtered) ==";   tree -a -F --prune -I "$(echo "$IGNORE" | tr -d '\n' | sed 's/|$//')";   echo; echo "== LOG FILE INDEX (directory → count) ==";   find . -type f \( -name '*.log' -o -name '*.log.*' -o -name '*.gz' -o -name '*.bz2' -o -name '*.xz' \)     -printf '%h\n' | sort | uniq -c | sort -nr;   echo; echo "== DOCX FILES ==";   find . -type f \( -iname '*.docx' -o -iname '*.doc' \) -print | sort; } | tee "$out"
#1760645841
cd /home/notesao && out="notesao_tree_$(date +%Y%m%d).txt" && IGNORE='
*.log|*.log.*|*.gz|*.bz2|*.xz|*.zip|*.7z|*.rar|*.tar|*.tar.*|*.tgz|*.txz|
*.bak|*.old|*.tmp|*.swp|*.iso|*.deb|*.rpm|*.whl|
AbsenceReport_*.pdf|absence_date_time*.pdf|absence_date_time*.html|completion_*.html|
node_modules|vendor|__pycache__|.venv|venv|venv311|new_venv|build|dist|
.git|.vscode|.vscode-server|.vscode-server-insiders|.idea|.pytest_cache|.ruff_cache|.mypy_cache|.tox|coverage|
.cache|.npm|.local|.cpanel|.softaculous|.spamassassin|.ssh|.trash|.caldav|.dotnet|
http-v2|LibreOffice_*|.config/libreoffice
' && {   echo "== FILE TREE (filtered) ==";   tree -a -F --prune -I "$(echo "$IGNORE" | tr -d '\n' | sed 's/|$//')";   echo; echo "== LOG FILE INDEX (directory → count) ==";   find . -type f \( -name '*.log' -o -name '*.log.*' -o -name '*.gz' -o -name '*.bz2' -o -name '*.xz' \)     -printf '%h\n' | sort | uniq -c | sort -nr;   echo; echo "== DOCX FILES ==";   find . -type f \( -iname '*.docx' -o -iname '*.doc' \) -print | sort; } | tee "$out"
#1760645921
cd /home/notesao && out="notesao_tree_$(date +%Y%m%d).txt" && IGNORE='
*.log|*.log.*|*.gz|*.bz2|*.xz|*.zip|*.7z|*.rar|*.tar|*.tar.*|*.tgz|*.txz|
*.bak|*.old|*.tmp|*.swp|*.iso|*.deb|*.rpm|*.whl|
AbsenceReport_*.pdf|absence_date_time*.pdf|absence_date_time*.html|completion_*.html|
node_modules|vendor|__pycache__|.venv|venv|venv311|new_venv|build|dist|tmp|
.git|.vscode|.vscode-server|.vscode-server-insiders|.idea|.pytest_cache|.ruff_cache|.mypy_cache|.tox|coverage|
.cache|.npm|.local|.cpanel|.softaculous|.spamassassin|.ssh|.trash|.caldav|.dotnet|
http-v2|LibreOffice_*|.config/libreoffice
' && {   echo "== FILE TREE (filtered) ==";   tree -a -F --prune -I "$(echo "$IGNORE" | tr -d '\n' | sed 's/|$//')";   echo; echo "== LOG FILE INDEX (directory → count) ==";   find . -type f \( -name '*.log' -o -name '*.log.*' -o -name '*.gz' -o -name '*.bz2' -o -name '*.xz' \)     -printf '%h\n' | sort | uniq -c | sort -nr;   echo; echo "== DOCX FILES ==";   find . -type f \( -iname '*.docx' -o -iname '*.doc' \) -print | sort; } | tee "$out"
#1760646151
cd /home/notesao && out="notesao_tree_$(date +%Y%m%d).txt" && IGNORE='
*.log|*.log.*|*.gz|*.bz2|*.xz|*.zip|*.7z|*.rar|*.tar|*.tar.*|*.tgz|*.txz|
*.bak|*.old|*.tmp|*.swp|*.iso|*.deb|*.rpm|*.whl|
AbsenceReport_*.pdf|absence_date_time*.pdf|absence_date_time*.html|completion_*.html|
node_modules|vendor|__pycache__|.venv|venv|venv311|new_venv|build|dist|tmp|
.git|.vscode|.vscode-server|.vscode-server-insiders|.idea|.pytest_cache|.ruff_cache|.mypy_cache|.tox|coverage|
.cache|.npm|.local|.cpanel|.softaculous|.spamassassin|.ssh|.trash|.caldav|.dotnet|
http-v2|LibreOffice_*|.config/libreoffice|GeneratedDocuments
' && {   echo "== FILE TREE (filtered) ==";   tree -a -F --prune -I "$(echo "$IGNORE" | tr -d '\n' | sed 's/|$//')";   echo; echo "== LOG FILE INDEX (directory → count) ==";   find . -type f \( -name '*.log' -o -name '*.log.*' -o -name '*.gz' -o -name '*.bz2' -o -name '*.xz' \)     -printf '%h\n' | sort | uniq -c | sort -nr;   echo; echo "== DOCX FILES ==";   find . -type f \( -iname '*.docx' -o -iname '*.doc' \) -print | sort;   echo; echo "== GeneratedDocuments (folders only, contents hidden) ==";   find . -type d -name 'GeneratedDocuments' -print | sort; } | tee "$out"
#1760646277
cd /home/notesao && out="notesao_tree_$(date +%Y%m%d).txt" && IGNORE='
*.log|*.log.*|error_log|error_log*|
*.gz|*.bz2|*.xz|*.zip|*.7z|*.rar|*.tar|*.tar.*|*.tgz|*.txz|
*.bak|*.old|*.tmp|*.swp|*.iso|*.deb|*.rpm|*.whl|*.class|*.jar|*.pack|
AbsenceReport_*.pdf|absence_*.html|absence_date_time*.pdf|absence_date_time*.html|completion_*.html|
node_modules|vendor|__pycache__|.venv|venv|venv311|new_venv|build|dist|tmp|
.git|.vscode|.vscode-server|.vscode-server-insiders|.idea|.pytest_cache|.ruff_cache|.mypy_cache|.tox|coverage|
.cache|.npm|.local|.cpanel|.cpanel-logs|.softaculous|.spamassassin|.ssh|.trash|.caldav|.dotnet|
http-v2|LibreOffice_*|.config/libreoffice|uno_packages|.codex|
backups|
*/public_html/uploads|*/public_html/uploads/*|uploads|uploads/*|
phpinfo.php|
*.png|*.jpg|*.jpeg|*.gif|*.svg|*.webp|*.ico|
GeneratedDocuments
' && {   echo "== FILE TREE (filtered) ==";   tree -a -F --prune -I "$(echo "$IGNORE" | tr -d '\n' | sed 's/|$//')";   echo; echo "== LOG FILE INDEX (directory → count) ==";   find . -type f \( -name '*.log' -o -name '*.log.*' -o -name 'error_log' -o -name 'error_log*' \)     -printf '%h\n' | sort | uniq -c | sort -nr;   echo; echo "== DOCX FILES ==";   find . -type f \( -iname '*.docx' -o -iname '*.doc' \) -print | sort;   echo; echo "== GeneratedDocuments (folders only) ==";   find . -type d -name 'GeneratedDocuments' -print | sort; } | tee "$out"
#1760646572
cd /home/notesao && out="notesao_tree_$(date +%Y%m%d).txt" && IGNORE='
*.log|*.log.*|error_log|error_log*|
*.gz|*.bz2|*.xz|*.zip|*.7z|*.rar|*.tar|*.tar.*|*.tgz|*.txz|
*.bak|*.old|*.tmp|*.swp|*.iso|*.deb|*.rpm|*.whl|*.class|*.jar|*.pack|
AbsenceReport_*.pdf|absence_*.html|absence_date_time*.pdf|absence_date_time*.html|completion_*.html|
node_modules|vendor|__pycache__|.venv|venv|venv311|new_venv|build|dist|tmp|
.git|.vscode|.vscode-server|.vscode-server-insiders|.idea|.pytest_cache|.ruff_cache|.mypy_cache|.tox|coverage|
.cache|.npm|.local|.cpanel|.cpanel-logs|.softaculous|.spamassassin|.ssh|.trash|.caldav|.dotnet|
http-v2|LibreOffice_*|.config/libreoffice|uno_packages|.codex|
backups|uploads|GeneratedDocuments|mail|Maildir
' && {   echo "== FILE TREE (filtered; mail files omitted) ==";   tree -a -F --prune -I "$(echo "$IGNORE" | tr -d '\n' | sed 's/|$//')";   echo; echo "== MAIL (folders only) ==";   [ -d mail ] && tree -a -d -F --prune mail;   [ -d Maildir ] && tree -a -d -F --prune Maildir;   echo; echo "== LOG FILE INDEX (directory → count) ==";   find . -type f \( -name '*.log' -o -name '*.log.*' -o -name 'error_log' -o -name 'error_log*' \)     -printf '%h\n' | sort | uniq -c | sort -nr;   echo; echo "== DOCX FILES ==";   find . -type f \( -iname '*.docx' -o -iname '*.doc' \) -print | sort; } | tee "$out"
#1760554250
tail -n 200 /home/notesao/dwag-link-debug.log
#1760555259
tail -n 200 /home/notesao/logs/dwag_notesao_com.php.error.log | egrep 'DWAG resolve|DWAG resolved link'
#1760557038
sudo -u notesao bash -lc ' find /home/notesao -type d -name .git -prune -print | sed "s|/\.git$||" | sort '
#1760557220
sudo -u notesao bash -lc '
mkdir -p /home/notesao/backups
ts=$(date +"%Y%m%d-%H%M%S")
tar -czf /home/notesao/backups/pre-git-clean-$ts.tgz /home/notesao
'
#1760558102
pgrep -u notesao -a tar || echo "no tar process"
#1760558131
ls -lh /home/notesao/backups/
#1760558137
tar -tzf /home/notesao/backups/pre-git-clean-20251015-144020.tgz | head -n 20
#1760558179
sudo -u notesao bash -lc '
set -euo pipefail
ts=$(date +"%Y%m%d-%H%M%S")
cd /home
tar -czf /home/notesao/backups/pre-git-clean-fixed-$ts.tgz \
  --exclude=notesao/backups \
  --exclude-vcs \
  notesao
'
#1760720005
curl -fsSL --connect-timeout 10 --max-time 300   "https://<domain>/absence-logic.php?mode=session&from=2000-01-01&to=$(date +%F)&grace_days=6&dry_run=1"
#1760720056
curl -fsSL --connect-timeout 10 --max-time 300 \ "https://saf.notesao.com/absence-logic.php?mode=session&from=2000-01-01&to=$(date +%F)&grace_days=6&dry_run=1"
#1760720127
bash -x /home/notesao/safatherhood/scripts/create_absence.sh
#1760720128
log=$(ls -t /home/notesao/safatherhood/scripts/logs/absence_*.html | head -1)
#1760720128
grep -E "Checking attendance|insertAbsenceRecord|awaiting|ERROR|Fatal" "$log" | tail -200
#1760720340
bash -x /home/notesao/safatherhood/scripts/create_absence.sh
#1760720340
log=$(ls -t /home/notesao/safatherhood/scripts/logs/absence_*.html | head -1)
#1760720340
grep -E "insertAbsenceRecord|ERROR|Fatal" "$log" | tail -200
#1760720677
bash -x /home/notesao/safatherhood/scripts/create_absence.sh
#1760720677
log=$(ls -t /home/notesao/safatherhood/scripts/logs/absence_*.html | head -1)
#1760720677
grep -E "insertAbsenceRecord|ERROR|Fatal" "$log" | tail -200
#1760721702
https://safatherhood.notesao.com/absence_logic.php?dry_run=1&grace_days=6
#1760722294
bash -x /home/notesao/safatherhood/scripts/create_absence.sh
#1760722294
log=$(ls -t /home/notesao/safatherhood/scripts/logs/absence_*.html | head -1)
#1760722294
grep -E "Checking attendance|INSERTED|Would INSERT|Skip|awaiting|ERROR|Fatal" "$log" | tail -200
#1760722734
bash -x /home/notesao/safatherhood/scripts/create_absence.sh
#1760722734
log=$(ls -t /home/notesao/safatherhood/scripts/logs/absence_*.html | head -1)
#1760722734
grep -E "INSERTED|Would INSERT|Skip|EXCUSED|ERROR|Fatal" "$log" | tail -300
#1760723930
bash -x /home/notesao/safatherhood/scripts/create_absence.sh
#1760723930
log=$(ls -t /home/notesao/safatherhood/scripts/logs/absence_*.html | head -1)
#1760723930
grep -E "INSERTED|EXCUSED|Would INSERT|Skip|ERROR|Fatal" "$log" | tail -300
#1761316080
bash -lc 'cd /home/notesao; comm -23 <(cd ffltest/public_html && find . -type f -printf "%P\n" | sort) <(cd lakeview/public_html && find . -type f -printf "%P\n" | sort) | sed "s#^#/home/notesao/ffltest/public_html/#"'
#1761316088
bash -lc 'cd /home/notesao; comm -23 <(cd ffltest/public_html && find . -type f \( -name "*.php" \) -printf "%P\n" | sort) <(cd lakeview/public_html && find . -type f \( -name "*.php" \) -printf "%P\n" | sort) | sed "s#^#/home/notesao/ffltest/public_html/#"'
#1761324947
grep -RinI -i -E 'required_?sessions?' /home/notesao/lakeview/   --binary-files=without-match   --exclude-dir={.git,vendor,node_modules,storage,logs,cache,backups}   --exclude='*.{log,csv,tsv,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,tar,tar.gz,gz,bz2,7z}'
#1761325587
php -v
#1761326903
grep -RinI -i -E 'required_?sessions?' /home/notesao/lakeview/   --binary-files=without-match   --exclude-dir={.git,vendor,node_modules,storage,logs,cache,backups}   --exclude='*.{log,csv,tsv,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,tar,tar.gz,gz,bz2,7z}'
#1761340954
grep -RInI -E 'ffl|free[ _-]?for[ _-]?life' /home/notesao/lakeview/ 2>/dev/null
#1761340990
grep -RInI --include='*.php' -E 'ffl|free[ _-]?for[ _-]?life' /home/notesao/lakeview/ 2>/dev/null
