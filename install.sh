
if [[ $EUID -ne 0 ]]; then
    echo -e "\033[31m[ERROR]\033[0m Please run this script as \033[1mroot\033[0m."
    exit 1
fi

LOG_FILE="/var/log/mirza_installer.log"

init_logging() {
    local log_dir
    log_dir="$(dirname "$LOG_FILE")"
    [ -d "$log_dir" ] || mkdir -p "$log_dir"
    [ -f "$LOG_FILE" ] || touch "$LOG_FILE"
    chmod 600 "$LOG_FILE"
}

log_message() {
    local level="$1"; shift
    local message="$*"
    local timestamp
    local color
    timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
    case "$level" in
        INFO) color="\033[1;34m" ;;
        WARN) color="\033[1;33m" ;;
        ERROR) color="\033[1;31m" ;;
        ACTION) color="\033[1;36m" ;;
        *) color="\033[0m" ;;
    esac
    echo -e "${color}[${level}]\033[0m $message"
    printf '%s [%s] %s\n' "$timestamp" "$level" "$message" >>"$LOG_FILE"
}

log_action() { log_message "ACTION" "$@"; }
log_info() { log_message "INFO" "$@"; }
log_warn() { log_message "WARN" "$@"; }
log_error() { log_message "ERROR" "$@"; }

init_logging
log_info "Mirza installer initialized (PID $$)"
type_text() {
    local text="$1"
    local delay="${2:-0.03}"
    local i=0
    while [ $i -lt ${#text} ]; do
        echo -n "${text:$i:1}"
        sleep "$delay"
        ((i++))
    done
    echo ""
}

type_text_colored() {
    local color="$1"
    local text="$2"
    local delay="${3:-0.03}"
    echo -ne "$color"
    type_text "$text" "$delay"
    echo -ne "\033[0m"
}

function show_animated_logo() {
    clear
    echo ""
    sleep 0.05
    type_text_colored "\033[1;32m" "███╗░░░███╗ ██╗ ██████╗░ ███████╗ ░█████╗░ ██████╗░ ██████╗░ ░█████╗░" 0.003
    type_text_colored "\033[1;32m" "████╗░████║ ██║ ██╔══██╗ ╚════██║ ██╔══██╗ ██╔══██╗ ██╔══██╗ ██╔══██╗" 0.003
    type_text_colored "\033[1;37m" "██╔████╔██║ ██║ ██████╔╝ ░░███╔═╝ ███████║ ██████╔╝ ██████╔╝ ██║░░██║" 0.003
    type_text_colored "\033[1;37m" "██║╚██╔╝██║ ██║ ██╔══██╗ ██╔══╝░░ ██╔══██║ ██╔═══╝░ ██╔══██╗ ██║░░██║" 0.003
    type_text_colored "\033[1;31m" "██║░╚═╝░██║ ██║ ██║░░██║ ███████╗ ██║░░██║ ██║░░░░░ ██║░░██║ ╚█████╔╝" 0.003
    type_text_colored "\033[1;31m" "╚═╝░░░░░╚═╝ ╚═╝ ╚═╝░░╚═╝ ╚══════╝ ╚═╝░░╚═╝ ╚═╝░░░░░ ╚═╝░░╚═╝ ░╚════╝░" 0.003
    echo ""
    type_text_colored "\033[1;33m" "                    Mirza Pro Bot Installer v3.9" 0.015
    type_text_colored "\033[1;36m" "                    Developer: mahdiMGF2" 0.015
    type_text_colored "\033[1;36m" "                    debugger:  github.com/Mmd-Amir/mirza_pro" 0.015
    echo ""
}



check_ssl_status() {
    if [ -f "/var/www/html/mirzabotconfig/config.php" ]; then
        domain=$(grep '^\$domainhosts' "/var/www/html/mirzabotconfig/config.php" | cut -d"'" -f2 | cut -d'/' -f1)

        if [ -n "$domain" ] && [ -f "/etc/letsencrypt/live/$domain/cert.pem" ]; then
            expiry_date=$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/$domain/cert.pem" | cut -d= -f2)
            current_date=$(date +%s)
            expiry_timestamp=$(date -d "$expiry_date" +%s)
            days_remaining=$(( ($expiry_timestamp - $current_date) / 86400 ))
            if [ $days_remaining -gt 0 ]; then
                echo -e "\033[32m✅ SSL Certificate: $days_remaining days remaining (Domain: $domain)\033[0m"
            else
                echo -e "\033[31m❌ SSL Certificate: Expired (Domain: $domain)\033[0m"
            fi
        else
            echo -e "\033[33m⚠️ SSL Certificate: Not found for domain $domain\033[0m"
        fi
    else
        echo -e "\033[33m⚠️ Cannot check SSL: Config file not found\033[0m"
    fi
}

check_bot_status() {
    if [ -f "/var/www/html/mirzabotconfig/config.php" ]; then
        echo -e "\033[32m✅ Bot is installed\033[0m"
        check_ssl_status
    else
        echo -e "\033[31m❌ Bot is not installed\033[0m"
    fi
}

configure_apache_vhost() {
    local domain="$1"
    local docroot="${2:-/var/www/html}"
    local conf="/etc/apache2/sites-available/${domain}.conf"

    if [ -z "$domain" ]; then
        echo -e "\033[31m[ERROR] Domain name missing while configuring Apache.\033[0m"
        return 1
    fi

    echo -e "\033[33mConfiguring Apache virtual host for ${domain} (DocumentRoot: ${docroot})...\033[0m"
    sudo tee "$conf" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $docroot

    <Directory $docroot>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
EOF

    if ! sudo a2ensite "${domain}.conf" >/dev/null 2>&1; then
        echo -e "\033[31m[ERROR] Failed to enable Apache site for ${domain}.\033[0m"
        return 1
    fi

    if ! sudo apache2ctl configtest >/dev/null 2>&1; then
        echo -e "\033[31m[ERROR] Apache configuration test failed after adding ${domain}.\033[0m"
        return 1
    fi

    return 0
}

cleanup_apache_state() {
    if pgrep -x apache2 >/dev/null 2>&1 && ! sudo systemctl is-active --quiet apache2; then
        echo -e "\033[33m[INFO] Detected Apache running outside systemd. Stopping stale process...\033[0m"
        sudo apachectl stop >/dev/null 2>&1 || sudo pkill -TERM apache2 >/dev/null 2>&1
        sudo rm -f /var/run/apache2/apache2.pid >/dev/null 2>&1
    fi
}

restore_apache_service() {
    cleanup_apache_state
    echo -e "\033[33mRe-enabling Apache service...\033[0m"
    sudo systemctl enable apache2 >/dev/null 2>&1 || echo -e "\033[33m[INFO] Apache service was already disabled.\033[0m"
    echo -e "\033[33mStarting Apache service...\033[0m"
    if ! sudo systemctl start apache2; then
        echo -e "\033[33m[WARN] Apache failed to start cleanly, retrying after cleanup...\033[0m"
        cleanup_apache_state
        if ! sudo systemctl start apache2; then
            echo -e "\033[31m[ERROR] Failed to start Apache service!\033[0m"
        fi
    fi
}

wait_for_certbot() {
    local timeout="${1:-180}"
    local interval=5
    local waited=0
    local lock_paths=(
        "/var/log/letsencrypt/.certbot.lock"
        "/var/lib/letsencrypt/.certbot.lock"
    )

    while true; do
        local lock_found=0
        for lock_file in "${lock_paths[@]}"; do
            if [ -f "$lock_file" ]; then
                lock_found=1
                break
            fi
        done
        if ! pgrep -x certbot >/dev/null 2>&1 && [ $lock_found -eq 0 ]; then
            return 0
        fi

        if [ $waited -ge $timeout ]; then
            log_error "Certbot lock has been held for more than ${timeout}s. Please ensure no other Certbot process is running."
            return 1
        fi

        log_warn "Certbot is already running; waiting for it to finish..."
        sleep $interval
        waited=$((waited + interval))
    done
}

function show_logo() {
    show_animated_logo
    return
}

print_menu_spacer() {
    local width=${1:-46}
    printf '\033[1;32m║\033[0m %*s \033[1;32m║\033[0m\n' "$width" ""
}

print_menu_option() {
    local option_number="$1"
    local option_label="$2"
    local color_code="${3:-1;37}"
    local width=${4:-46}

    printf -v padded_number '%-3s' "$option_number"
    local plain_text="$padded_number $option_label"
    local padding=$((width - ${#plain_text}))
    ((padding < 0)) && padding=0

    local colored_number=$'\033['"$color_code"'m'"$padded_number"$'\033[0m'
    printf '\033[1;32m║\033[0m %s %s%*s \033[1;32m║\033[0m\n' \
        "$colored_number" "$option_label" "$padding" ""
}




function immigration_install() {
    show_animated_logo

    echo ""
    type_text_colored "\033[1;32m" "╔════════════════════════════════════════════════╗" 0.01
    type_text_colored "\033[1;32m" "║      🚀 IMMIGRATION - WEB INSTALLER            ║" 0.01
    type_text_colored "\033[1;32m" "╚════════════════════════════════════════════════╝" 0.01
    echo ""

    type_text_colored "\033[1;33m" "Scanning for installed bots in /var/www/html/..."
    echo ""

    mapfile -t BOT_CONFIGS < <(find /var/www/html -maxdepth 4 -type f -name config.php -print 2>/dev/null | sort)

    VALID_CONFIGS=()
    for CONFIG_CHECK in "${BOT_CONFIGS[@]}"; do
        [ -z "$CONFIG_CHECK" ] && continue
        if grep -q '\$domainhosts' "$CONFIG_CHECK" 2>/dev/null; then
            VALID_CONFIGS+=("$CONFIG_CHECK")
        fi
    done

    if [ ${#VALID_CONFIGS[@]} -eq 0 ]; then
        type_text_colored "\033[1;31m" "✗ No bots found in /var/www/html/"
        echo ""
        read -p "Press Enter to return to main menu..."
        show_menu
        return 1
    fi

    BOT_COUNT=${#VALID_CONFIGS[@]}
    type_text_colored "\033[1;32m" "✓ Found $BOT_COUNT bot(s) installed"
    echo ""
    echo ""

    PROCESSED=0
    ERRORS=0

    for CONFIG_FILE in "${VALID_CONFIGS[@]}"; do
        [ -z "$CONFIG_FILE" ] && continue
        if [ ! -f "$CONFIG_FILE" ]; then
            continue
        fi
        BOT_PATH="$(dirname "$CONFIG_FILE")"
        RELATIVE_PATH="${BOT_PATH#/var/www/html/}"
        if [ "$BOT_PATH" = "/var/www/html" ]; then
            BOT_LABEL="/var/www/html"
        elif [ -z "$RELATIVE_PATH" ] || [ "$RELATIVE_PATH" = "$BOT_PATH" ]; then
            BOT_LABEL="$(basename "$BOT_PATH")"
        else
            BOT_LABEL="$RELATIVE_PATH"
        fi

        DOMAIN=$(grep -oP "\$domainhosts\s*=\s*[\'\"]\K[^\'\"]+" "$CONFIG_FILE" 2>/dev/null | head -1)

        if [ -z "$DOMAIN" ]; then
            DOMAIN=$(grep "domainhosts" "$CONFIG_FILE" | sed -n "s/.*domainhosts.*=.*[\'\"]\([^\'\"]*\)[\'\"].*/\1/p" | head -1)
        fi

        if [ -z "$DOMAIN" ]; then
            continue
        fi

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        type_text_colored "\033[1;36m" "Processing bot: $BOT_LABEL" 0.03
        echo ""

        type_text_colored "\033[1;32m" "  ✓ Domain detected: $DOMAIN" 0.03
        echo ""

        type_text_colored "\033[1;33m" "  Setting file permissions..." 0.03

        if [ -f "$CONFIG_FILE" ]; then
            sudo chmod 666 "$CONFIG_FILE" 2>/dev/null || {
                type_text_colored "\033[1;31m" "  ✗ Failed to set permissions for config.php"
                echo ""
                ((ERRORS++))
                continue
            }
        fi

        TABLE_FILE="$BOT_PATH/table.php"
        if [ -f "$TABLE_FILE" ]; then
            sudo chmod 644 "$TABLE_FILE" 2>/dev/null
        fi

        sudo chmod 755 "$BOT_PATH" 2>/dev/null || {
            type_text_colored "\033[1;31m" "  ✗ Failed to set directory permissions"
            echo ""
            ((ERRORS++))
            continue
        }

        sudo chown -R www-data:www-data "$BOT_PATH" 2>/dev/null || {
            type_text_colored "\033[1;31m" "  ✗ Failed to set ownership"
            echo ""
            ((ERRORS++))
            continue
        }

        APP_DIR=""
        if [[ "$BOT_PATH" == */app ]]; then
            APP_DIR="$BOT_PATH"
        elif [[ "$BOT_PATH" == */app/* ]]; then
            APP_DIR="${BOT_PATH%%/app/*}/app"
        fi

        if [ -n "$APP_DIR" ] && [ -d "$APP_DIR" ]; then
            type_text_colored "\033[1;33m" "  Applying permissions to app directory: $APP_DIR" 0.02
            sudo chmod 755 "$APP_DIR" 2>/dev/null || {
                type_text_colored "\033[1;31m" "  ✗ Failed to set directory permissions for app"
                echo ""
                ((ERRORS++))
                continue
            }
            sudo chown -R www-data:www-data "$APP_DIR" 2>/dev/null || {
                type_text_colored "\033[1;31m" "  ✗ Failed to set ownership for app directory"
                echo ""
                ((ERRORS++))
                continue
            }
        fi

        type_text_colored "\033[1;32m" "  ✓ Permissions set successfully" 0.03
        echo ""

        DOMAIN_TRIMMED="${DOMAIN%/}"
        if [[ "$DOMAIN_TRIMMED" =~ ^https?:// ]]; then
            INSTALLER_URL="${DOMAIN_TRIMMED}/installer/"
        else
            INSTALLER_URL="https://${DOMAIN_TRIMMED}/installer/"
        fi

        type_text_colored "\033[1;36m" "  📎 Installer URL:" 0.03
        type_text_colored "\033[1;33m" "     $INSTALLER_URL" 0.02
        echo ""

        ((PROCESSED++))
    done

    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""

    type_text_colored "\033[1;32m" "╔════════════════════════════════════════════════╗" 0.01
    type_text_colored "\033[1;32m" "║               SUMMARY                         ║" 0.01
    type_text_colored "\033[1;32m" "╚════════════════════════════════════════════════╝" 0.01
    echo ""

    type_text_colored "\033[1;37m" "Total bots found:       $BOT_COUNT"
    type_text_colored "\033[1;32m" "Successfully processed: $PROCESSED"
    if [ $ERRORS -gt 0 ]; then
        type_text_colored "\033[1;31m" "Errors encountered:     $ERRORS"
    fi
    echo ""
    echo ""

    type_text_colored "\033[1;33m" "Open the installer URLs in your browser to complete the migration."
    echo ""

    read -p "Press Enter to return to main menu..."
    show_menu
}


function show_menu() {
    show_logo
    check_ssl_status

    type_text_colored "\033[1;32m" "╔════════════════════════════════════════════════╗" 0.005
    type_text_colored "\033[1;32m" "║            MIRZA PRO - MAIN MENU               ║" 0.005
    type_text_colored "\033[1;32m" "╠════════════════════════════════════════════════╣" 0.005
    print_menu_spacer
    print_menu_option "1)" "Install Mirza Bot"
    print_menu_option "2)" "Update Mirza Bot"
    print_menu_option "3)" "Remove Mirza Bot"
    print_menu_option "4)" "Export Database"
    print_menu_option "5)" "Import Database"
    print_menu_option "6)" "Configure Automated Backup"
    print_menu_option "7)" "Renew SSL Certificates"
    print_menu_option "8)" "Change Domain"
    print_menu_option "9)" "Additional Bot Management"
    print_menu_option "10)" "Immigration (Server Migration)" "1;33"
    print_menu_option "11)" "Remove Domain" "1;31"
    print_menu_option "12)" "Delete Cron Jobs" "1;31"
    print_menu_option "13)" "Exit" "1;31"
    type_text_colored "\033[1;32m" "╚════════════════════════════════════════════════╝" 0.005
    echo ""
    read -p "$(echo -e '\033[1;33m❯\033[0m Select an option [1-13]: ')" option
    case $option in
        1) install_bot ;;
        2) update_bot ;;
        3) remove_bot ;;
        4) export_database ;;
        5) import_database ;;
        6) auto_backup ;;
        7) renew_ssl ;;
        8) change_domain ;;
        9) manage_additional_bots ;;
        10) immigration_install ;;
        11) remove_domain ;;
        12) delete_cron_jobs ;;
        13)
            type_text_colored "\033[1;32m" "✓ Exiting... Goodbye!" 0.05
            exit 0
            ;;
        *)
            type_text_colored "\033[1;31m" "✗ Invalid option. Please try again."
            sleep 2
            show_menu
            ;;
    esac
}

function check_marzban_installed() {
    if [ -f "/opt/marzban/docker-compose.yml" ]; then
        return 0 
    else
        return 1  
    fi
}

function detect_database_type() {
    COMPOSE_FILE="/opt/marzban/docker-compose.yml"
    if [ ! -f "$COMPOSE_FILE" ]; then
        echo "unknown"  
        return 1
    fi
    if grep -q "^[[:space:]]*mysql:" "$COMPOSE_FILE"; then
        echo "mysql"
        return 0
    elif grep -q "^[[:space:]]*mariadb:" "$COMPOSE_FILE"; then
        echo "mariadb"
        return 1
    else
        echo "sqlite"  
        return 1
    fi
}

function find_free_port() {
    for port in {3300..3330}; do
        if ! ss -tuln | grep -q ":$port "; then
            echo "$port"
            return 0
        fi
    done
    echo -e "\033[31m[ERROR] No free port found between 3300 and 3330.\033[0m"
    exit 1
}
function fix_update_issues() {
    echo -e "\e[33mTrying to fix update issues by changing mirrors...\033[0m"

    cp /etc/apt/sources.list /etc/apt/sources.list.backup

    if [ -f /etc/os-release ]; then
        . /etc/apt/sources.list
        VERSION_ID=$(cat /etc/os-release | grep VERSION_ID | cut -d '"' -f2)
        UBUNTU_CODENAME=$(cat /etc/os-release | grep UBUNTU_CODENAME | cut -d '=' -f2)
    else
        echo -e "\e[91mCould not detect Ubuntu version.\033[0m"
        return 1
    fi

    MIRRORS=(
        "archive.ubuntu.com"
        "us.archive.ubuntu.com"
        "fr.archive.ubuntu.com"
        "de.archive.ubuntu.com"
        "mirrors.digitalocean.com"
        "mirrors.linode.com"
    )

    for mirror in "${MIRRORS[@]}"; do
        echo -e "\e[33mTrying mirror: $mirror\033[0m"
        cat > /etc/apt/sources.list << EOF
deb http://$mirror/ubuntu/ $UBUNTU_CODENAME main restricted universe multiverse
deb http://$mirror/ubuntu/ $UBUNTU_CODENAME-updates main restricted universe multiverse
deb http://$mirror/ubuntu/ $UBUNTU_CODENAME-security main restricted universe multiverse
EOF

        if apt-get update 2>/dev/null; then
            echo -e "\e[32mSuccessfully updated using mirror: $mirror\033[0m"
            return 0
        fi
    done

    mv /etc/apt/sources.list.backup /etc/apt/sources.list
    echo -e "\e[91mAll mirrors failed. Restored original sources.list\033[0m"
    return 1
}

function install_bot() {
    echo -e "\e[32mInstalling mirza script ... \033[0m\n"

    if check_marzban_installed; then
        echo -e "\033[41m[IMPORTANT WARNING]\033[0m \033[1;33mMarzban detected. Proceeding with Marzban-compatible installation.\033[0m"
        install_bot_with_marzban "$@" 
        return 0
    fi

    add_php_ppa() {
        sudo add-apt-repository -y ppa:ondrej/php || {
            echo -e "\e[91mError: Failed to add PPA ondrej/php.\033[0m"
            return 1
        }
    }

    add_php_ppa_with_locale() {
        sudo LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php || {
            echo -e "\e[91mError: Failed to add PPA ondrej/php with locale override.\033[0m"
            return 1
        }
    }

    if ! add_php_ppa; then
        echo "Failed to add PPA with default locale, retrying with locale override..."
        if ! add_php_ppa_with_locale; then
            echo "Failed to add PPA even with locale override. Exiting..."
            exit 1
        fi
    fi

    if ! (sudo apt update && sudo apt upgrade -y); then
        echo -e "\e[93mUpdate/upgrade failed. Attempting to fix using alternative mirrors...\033[0m"
        if fix_update_issues; then
            if sudo apt update && sudo apt upgrade -y; then
                echo -e "\e[92mThe server was successfully updated after fixing mirrors...\033[0m\n"
            else
                echo -e "\e[91mError: Failed to update even after trying alternative mirrors.\033[0m"
                exit 1
            fi
        else
            echo -e "\e[91mError: Failed to update/upgrade packages and mirror fix failed.\033[0m"
            exit 1
        fi
    else
        echo -e "\e[92mThe server was successfully updated ...\033[0m\n"
    fi

    sudo apt-get install software-properties-common || {
        echo -e "\e[91mError: Failed to install software-properties-common.\033[0m"
        exit 1
    }

    sudo apt install -y git unzip curl || {
        echo -e "\e[91mError: Failed to install required packages.\033[0m"
        exit 1
    }

    DEBIAN_FRONTEND=noninteractive sudo apt install -y php8.2 php8.2-fpm php8.2-mysql || {
        echo -e "\e[91mError: Failed to install PHP 8.2 and related packages.\033[0m"
        exit 1
    }

    PKG=(
        lamp-server^
        libapache2-mod-php
        mysql-server
        apache2
        php-mbstring
        php-zip
        php-gd
        php-json
        php-curl
    )

    for i in "${PKG[@]}"; do
        dpkg -s $i &>/dev/null
        if [ $? -eq 0 ]; then
            echo "$i is already installed"
        else
            if ! DEBIAN_FRONTEND=noninteractive sudo apt install -y $i; then
                echo -e "\e[91mError installing $i. Exiting...\033[0m"
                exit 1
            fi
        fi
    done

    echo -e "\n\e[92mPackages Installed, Continuing ...\033[0m\n"

    echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | sudo debconf-set-selections
    echo 'phpmyadmin phpmyadmin/app-password-confirm password mirzahipass' | sudo debconf-set-selections
    echo 'phpmyadmin phpmyadmin/mysql/admin-pass password mirzahipass' | sudo debconf-set-selections
    echo 'phpmyadmin phpmyadmin/mysql/app-pass password mirzahipass' | sudo debconf-set-selections
    echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2' | sudo debconf-set-selections

    sudo apt-get install phpmyadmin -y || {
        echo -e "\e[91mError: Failed to install phpMyAdmin.\033[0m"
        exit 1
    }
    if [ -f /etc/apache2/conf-available/phpmyadmin.conf ]; then
        sudo rm -f /etc/apache2/conf-available/phpmyadmin.conf && echo -e "\e[92mRemoved existing phpMyAdmin configuration.\033[0m"
    fi

    sudo ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf || {
        echo -e "\e[91mError: Failed to create symbolic link for phpMyAdmin configuration.\033[0m"
        exit 1
    }

    sudo a2enconf phpmyadmin.conf || {
        echo -e "\e[91mError: Failed to enable phpMyAdmin configuration.\033[0m"
        exit 1
    }
    sudo systemctl restart apache2 || {
        echo -e "\e[91mError: Failed to restart Apache2 service.\033[0m"
        exit 1
    }

    sudo apt-get install -y php-soap || {
        echo -e "\e[91mError: Failed to install php-soap.\033[0m"
        exit 1
    }

    sudo apt-get install libapache2-mod-php || {
        echo -e "\e[91mError: Failed to install libapache2-mod-php.\033[0m"
        exit 1
    }

    sudo systemctl enable mysql.service || {
        echo -e "\e[91mError: Failed to enable MySQL service.\033[0m"
        exit 1
    }
    sudo systemctl start mysql.service || {
        echo -e "\e[91mError: Failed to start MySQL service.\033[0m"
        exit 1
    }
    sudo systemctl enable apache2 || {
        echo -e "\e[91mError: Failed to enable Apache2 service.\033[0m"
        exit 1
    }
    sudo systemctl start apache2 || {
        echo -e "\e[91mError: Failed to start Apache2 service.\033[0m"
        exit 1
    }

    sudo apt-get install ufw -y || {
        echo -e "\e[91mError: Failed to install UFW.\033[0m"
        exit 1
    }
    ufw allow 'Apache' || {
        echo -e "\e[91mError: Failed to allow Apache in UFW.\033[0m"
        exit 1
    }
    sudo systemctl restart apache2 || {
        echo -e "\e[91mError: Failed to restart Apache2 service after UFW update.\033[0m"
        exit 1
    }

    sudo apt-get install -y git || {
        echo -e "\e[91mError: Failed to install Git.\033[0m"
        exit 1
    }
    sudo apt-get install -y wget || {
        echo -e "\e[91mError: Failed to install Wget.\033[0m"
        exit 1
    }
    sudo apt-get install -y unzip || {
        echo -e "\e[91mError: Failed to install Unzip.\033[0m"
        exit 1
    }
    sudo apt install curl -y || {
        echo -e "\e[91mError: Failed to install cURL.\033[0m"
        exit 1
    }
    sudo apt-get install -y php-ssh2 || {
        echo -e "\e[91mError: Failed to install php-ssh2.\033[0m"
        exit 1
    }
    sudo apt-get install -y libssh2-1-dev libssh2-1 || {
        echo -e "\e[91mError: Failed to install libssh2.\033[0m"
        exit 1
    }
    sudo apt install jq -y || {
        echo -e "\e[91mError: Failed to install jq.\033[0m"
        exit 1
    }

    sudo systemctl restart apache2.service || {
        echo -e "\e[91mError: Failed to restart Apache2 service.\033[0m"
        exit 1
    }

    BOT_DIR="/var/www/html/mirzabotconfig"
    if [ -d "$BOT_DIR" ]; then
        echo -e "\e[93mDirectory $BOT_DIR already exists. Removing...\033[0m"
        sudo rm -rf "$BOT_DIR" || {
            echo -e "\e[91mError: Failed to remove existing directory $BOT_DIR.\033[0m"
            exit 1
        }
    fi

    sudo mkdir -p "$BOT_DIR"
    if [ ! -d "$BOT_DIR" ]; then
        echo -e "\e[91mError: Failed to create directory $BOT_DIR.\033[0m"
        exit 1
    fi

    ZIP_URL=$(curl -s https://api.github.com/repos/Mmd-Amir/mirza_pro/releases/latest | grep "zipball_url" | cut -d '"' -f 4)

if [[ "$1" == "-v" && "$2" == "beta" ]] || [[ "$1" == "-beta" ]] || [[ "$1" == "-" && "$2" == "beta" ]]; then
    ZIP_URL="https://github.com/Mmd-Amir/mirza_pro/archive/refs/heads/main.zip"
elif [[ "$1" == "-v" && -n "$2" ]]; then
    ZIP_URL="https://github.com/Mmd-Amir/mirza_pro/mirza_pro/archive/refs/tags/$2.zip"
fi

    TEMP_DIR="/tmp/mirzabot"
    mkdir -p "$TEMP_DIR"
    wget -O "$TEMP_DIR/bot.zip" "$ZIP_URL" || {
        echo -e "\e[91mError: Failed to download the specified version.\033[0m"
        exit 1
    }

    unzip "$TEMP_DIR/bot.zip" -d "$TEMP_DIR"
    EXTRACTED_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d)
    mv "$EXTRACTED_DIR"/* "$BOT_DIR" || {
        echo -e "\e[91mError: Failed to move extracted files.\033[0m"
        exit 1
    }
    rm -rf "$TEMP_DIR"

    sudo chown -R www-data:www-data "$BOT_DIR"
sudo chmod -R 755 "$BOT_DIR"

echo -e "\n\033[33mMirza config and script have been installed successfully.\033[0m"



wait
if [ ! -d "/root/confmirza" ]; then
    sudo mkdir /root/confmirza || {
        echo -e "\e[91mError: Failed to create /root/confmirza directory.\033[0m"
        exit 1
    }

    sleep 1

    touch /root/confmirza/dbrootmirza.txt || {
        echo -e "\e[91mError: Failed to create dbrootmirza.txt.\033[0m"
        exit 1
    }
    sudo chmod -R 777 /root/confmirza/dbrootmirza.txt || {
        echo -e "\e[91mError: Failed to set permissions for dbrootmirza.txt.\033[0m"
        exit 1
    }
    sleep 1

    randomdbpasstxt=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)

    ASAS="$"

    echo "${ASAS}user = 'root';" >> /root/confmirza/dbrootmirza.txt
    echo "${ASAS}pass = '${randomdbpasstxt}';" >> /root/confmirza/dbrootmirza.txt
    echo "${ASAS}path = '${RANDOM_NUMBER}';" >> /root/confmirza/dbrootmirza.txt

    sleep 1

    passs=$(cat /root/confmirza/dbrootmirza.txt | grep '$pass' | cut -d"'" -f2)
    userrr=$(cat /root/confmirza/dbrootmirza.txt | grep '$user' | cut -d"'" -f2)

    sudo mysql -u $userrr -p$passs -e "alter user '$userrr'@'localhost' identified with mysql_native_password by '$passs';FLUSH PRIVILEGES;" || {
        echo -e "\e[91mError: Failed to alter MySQL user. Attempting recovery...\033[0m"

        sudo sed -i '$ a skip-grant-tables' /etc/mysql/mysql.conf.d/mysqld.cnf
        sudo systemctl restart mysql

        sudo mysql <<EOF
DROP USER IF EXISTS 'root'@'localhost';
CREATE USER 'root'@'localhost' IDENTIFIED BY '${passs}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

        sudo sed -i '/skip-grant-tables/d' /etc/mysql/mysql.conf.d/mysqld.cnf
        sudo systemctl restart mysql

        echo "SELECT 1" | mysql -u$userrr -p$passs 2>/dev/null || {
            echo -e "\e[91mError: Recovery failed. MySQL login still not working.\033[0m"
            exit 1
        }
    }

    echo "Folder created successfully!"
else
    echo "Folder already exists."
fi


clear

echo " "
echo -e "\e[32m SSL \033[0m\n"

read -p "Enter the domain: " domainname
while [[ ! "$domainname" =~ ^[a-zA-Z0-9.-]+$ ]]; do
    echo -e "\e[91mInvalid domain format. Please try again.\033[0m"
    read -p "Enter the domain: " domainname
done
    DOMAIN_NAME="$domainname"
    PATHS=$(cat /root/confmirza/dbrootmirza.txt | grep '$path' | cut -d"'" -f2)
    sudo ufw allow 80 || {
        echo -e "\e[91mError: Failed to allow port 80 in UFW.\033[0m"
        exit 1
    }
    sudo ufw allow 443 || {
        echo -e "\e[91mError: Failed to allow port 443 in UFW.\033[0m"
        exit 1
    }

    echo -e "\033[33mDisable apache2\033[0m"
    wait

    sudo systemctl stop apache2 || {
        echo -e "\e[91mError: Failed to stop Apache2.\033[0m"
        exit 1
    }
    sudo systemctl disable apache2 || {
        echo -e "\e[91mError: Failed to disable Apache2.\033[0m"
        exit 1
    }
    sudo apt install letsencrypt -y || {
        echo -e "\e[91mError: Failed to install letsencrypt.\033[0m"
        exit 1
    }
    sudo systemctl enable certbot.timer || {
        echo -e "\e[91mError: Failed to enable certbot timer.\033[0m"
        exit 1
    }
    if ! wait_for_certbot; then
        echo -e "\e[91mError: Certbot is busy. Please try again shortly.\033[0m"
        exit 1
    fi
    sudo certbot certonly --standalone --agree-tos --preferred-challenges http -d $DOMAIN_NAME || {
        echo -e "\e[91mError: Failed to generate SSL certificate.\033[0m"
        exit 1
    }
    sudo apt install python3-certbot-apache -y || {
        echo -e "\e[91mError: Failed to install python3-certbot-apache.\033[0m"
        exit 1
    }
    if ! wait_for_certbot; then
        echo -e "\e[91mError: Certbot is busy. Please try again shortly.\033[0m"
        exit 1
    fi
    sudo certbot --apache --agree-tos --preferred-challenges http -d $DOMAIN_NAME || {
        echo -e "\e[91mError: Failed to configure SSL with Certbot.\033[0m"
        exit 1
    }

    echo " "
    echo -e "\033[33mEnable apache2\033[0m"
    wait
    sudo systemctl enable apache2 || {
        echo -e "\e[91mError: Failed to enable Apache2.\033[0m"
        exit 1
    }
    sudo systemctl start apache2 || {
        echo -e "\e[91mError: Failed to start Apache2.\033[0m"
        exit 1
    }
            clear

        printf "\e[33m[+] \e[36mBot Token: \033[0m"
        read YOUR_BOT_TOKEN
        while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; do
            echo -e "\e[91mInvalid bot token format. Please try again.\033[0m"
            printf "\e[33m[+] \e[36mBot Token: \033[0m"
            read YOUR_BOT_TOKEN
        done

        printf "\e[33m[+] \e[36mChat id: \033[0m"
        read YOUR_CHAT_ID
        while [[ ! "$YOUR_CHAT_ID" =~ ^-?[0-9]+$ ]]; do
            echo -e "\e[91mInvalid chat ID format. Please try again.\033[0m"
            printf "\e[33m[+] \e[36mChat id: \033[0m"
            read YOUR_CHAT_ID
        done

        YOUR_DOMAIN="$DOMAIN_NAME"

    while true; do
        printf "\e[33m[+] \e[36musernamebot: \033[0m"
        read YOUR_BOTNAME
        if [ "$YOUR_BOTNAME" != "" ]; then
            break
        else
            echo -e "\e[91mError: Bot username cannot be empty. Please enter a valid username.\033[0m"
        fi
    done

    ROOT_PASSWORD=$(cat /root/confmirza/dbrootmirza.txt | grep '$pass' | cut -d"'" -f2)
    ROOT_USER="root"
    echo "SELECT 1" | mysql -u$ROOT_USER -p$ROOT_PASSWORD 2>/dev/null || {
        echo -e "\e[91mError: MySQL connection failed.\033[0m"
        exit 1
    }

    if [ $? -eq 0 ]; then
        wait

        randomdbpass=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)

        randomdbdb=$(openssl rand -base64 10 | tr -dc 'a-zA-Z' | cut -c1-8)

        if [[ $(mysql -u root -p$ROOT_PASSWORD -e "SHOW DATABASES LIKE 'mirzabot'") ]]; then
            clear
            echo -e "\n\e[91mYou have already created the database\033[0m\n"
        else
            dbname=mirzabot
            clear
            echo -e "\n\e[32mPlease enter the database username!\033[0m"
            printf "[+] Default user name is \e[91m${randomdbdb}\e[0m ( let it blank to use this user name ): "
            read dbuser
            if [ "$dbuser" = "" ]; then
                dbuser=$randomdbdb
            fi

            echo -e "\n\e[32mPlease enter the database password!\033[0m"
            printf "[+] Default password is \e[91m${randomdbpass}\e[0m ( let it blank to use this password ): "
            read dbpass
            if [ "$dbpass" = "" ]; then
                dbpass=$randomdbpass
            fi

            mysql -u root -p$ROOT_PASSWORD -e "CREATE DATABASE $dbname;" -e "CREATE USER '$dbuser'@'%' IDENTIFIED WITH mysql_native_password BY '$dbpass';GRANT ALL PRIVILEGES ON * . * TO '$dbuser'@'%';FLUSH PRIVILEGES;" -e "CREATE USER '$dbuser'@'localhost' IDENTIFIED WITH mysql_native_password BY '$dbpass';GRANT ALL PRIVILEGES ON * . * TO '$dbuser'@'localhost';FLUSH PRIVILEGES;" || {
                echo -e "\e[91mError: Failed to create database or user.\033[0m"
                exit 1
            }

            echo -e "\n\e[95mDatabase Created.\033[0m"

            clear



            ASAS="$"

            wait

            sleep 1

            file_path="/var/www/html/mirzabotconfig/config.php"

            if [ -f "$file_path" ]; then
              rm "$file_path" || {
                echo -e "\e[91mError: Failed to delete old config.php.\033[0m"
                exit 1
              }
              echo -e "File deleted successfully."
            else
              echo -e "File not found."
            fi

            sleep 1

            secrettoken=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)

            echo -e "<?php" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}APIKEY = '${YOUR_BOT_TOKEN}';" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}usernamedb = '${dbuser}';" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}passworddb = '${dbpass}';" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}dbname = '${dbname}';" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}domainhosts = '${YOUR_DOMAIN}/mirzabotconfig';" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}adminnumber = '${YOUR_CHAT_ID}';" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}usernamebot = '${YOUR_BOTNAME}';" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}secrettoken = '${secrettoken}';" >> /var/www/html/mirzabotconfig/config.php
            echo -e "${ASAS}connect = mysqli_connect('localhost', \$usernamedb, \$passworddb, \$dbname);" >> /var/www/html/mirzabotconfig/config.php
            echo -e "if (${ASAS}connect->connect_error) {" >> /var/www/html/mirzabotconfig/config.php
            echo -e "die(' The connection to the database failed:' . ${ASAS}connect->connect_error);" >> /var/www/html/mirzabotconfig/config.php
            echo -e "}" >> /var/www/html/mirzabotconfig/config.php
            echo -e "mysqli_set_charset(${ASAS}connect, 'utf8mb4');" >> /var/www/html/mirzabotconfig/config.php
            text_to_save=$(cat <<EOF
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
\$dsn = "mysql:host=localhost;dbname=${ASAS}dbname;charset=utf8mb4";
try {
     \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options);
} catch (\PDOException \$e) {
     throw new \PDOException(\$e->getMessage(), (int)\$e->getCode());
}
EOF
)
echo -e "$text_to_save" >> /var/www/html/mirzabotconfig/config.php
            echo -e "?>" >> /var/www/html/mirzabotconfig/config.php

            sleep 1

            curl -F "url=https://${YOUR_DOMAIN}/mirzabotconfig/index.php" \
     -F "secret_token=${secrettoken}" \
     "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook" || {
                echo -e "\e[91mError: Failed to set webhook for bot.\033[0m"
                exit 1
            }
            MESSAGE="✅ The bot is installed! for start the bot send /start command."
            curl -s -X POST "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/sendMessage" -d chat_id="${YOUR_CHAT_ID}" -d text="$MESSAGE" || {
                echo -e "\e[91mError: Failed to send message to Telegram.\033[0m"
                exit 1
            }

            sleep 1
            sudo systemctl start apache2 || {
                echo -e "\e[91mError: Failed to start Apache2.\033[0m"
                exit 1
            }
            url="https://${YOUR_DOMAIN}/mirzabotconfig/table.php"
            curl $url || {
                echo -e "\e[91mError: Failed to fetch URL from domain.\033[0m"
                exit 1
            }

            clear

            echo " "

            echo -e "\e[102mDomain Bot: https://${YOUR_DOMAIN}\033[0m"
            echo -e "\e[104mDatabase address: https://${YOUR_DOMAIN}/phpmyadmin\033[0m"
            echo -e "\e[33mDatabase name: \e[36m${dbname}\033[0m"
            echo -e "\e[33mDatabase username: \e[36m${dbuser}\033[0m"
            echo -e "\e[33mDatabase password: \e[36m${dbpass}\033[0m"
            echo " "
        fi


    elif [ "$ROOT_PASSWORD" = "" ] || [ "$ROOT_USER" = "" ]; then
        echo -e "\n\e[36mThe password is empty.\033[0m\n"
    else

        echo -e "\n\e[36mThe password is not correct.\033[0m\n"

    fi

    chmod +x /root/install.sh
    ln -sf /root/install.sh /usr/local/bin/mirza >/dev/null 2>&1

}

function install_bot_with_marzban() {
    echo -e "\033[41m[IMPORTANT WARNING]\033[0m \033[1;33mMarzban panel is detected on your server. Please make sure to backup the Marzban database before installing Mirza Bot.\033[0m"
    read -p "Are you sure you want to install Mirza Bot alongside Marzban? (y/n): " confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        echo -e "\e[91mInstallation aborted by user.\033[0m"
        exit 0
    fi

    echo -e "\e[32mChecking Marzban database type...\033[0m"
    DB_TYPE=$(detect_database_type)
    if [ "$DB_TYPE" != "mysql" ]; then
        echo -e "\e[91mError: Your database is $DB_TYPE. To install Mirza Bot, you must use MySQL.\033[0m"
        echo -e "\e[93mPlease configure Marzban to use MySQL and try again.\033[0m"
        exit 1
    fi
    echo -e "\e[92mMySQL detected. Proceeding with installation...\033[0m"

    echo -e "\e[32mChecking port availability...\033[0m"
    if sudo ss -tuln | grep -q ":80 "; then
        echo -e "\e[91mError: Port 80 is already in use. Please free port 80 and run the script again.\033[0m"
        exit 1
    fi
    if sudo ss -tuln | grep -q ":88 "; then
        echo -e "\e[91mError: Port 88 is already in use. Please free port 88 and run the script again.\033[0m"
        exit 1
    fi
    echo -e "\e[92mPorts 80 and 88 are free. Proceeding with installation...\033[0m"

    if ! (sudo apt update && sudo apt upgrade -y); then
        echo -e "\e[93mUpdate/upgrade failed. Attempting to fix using alternative mirrors...\033[0m"
        if fix_update_issues; then
            if sudo apt update && sudo apt upgrade -y; then
                echo -e "\e[92mSystem updated successfully after fixing mirrors...\033[0m\n"
            else
                echo -e "\e[91mError: Failed to update even after trying alternative mirrors.\033[0m"
                exit 1
            fi
        else
            echo -e "\e[91mError: Failed to update/upgrade system and mirror fix failed.\033[0m"
            exit 1
        fi
    else
        echo -e "\e[92mSystem updated successfully...\033[0m\n"
    fi

    sudo apt-get install software-properties-common || {
        echo -e "\e[91mError: Failed to install software-properties-common.\033[0m"
        exit 1
    }

    echo -e "\e[32mChecking and installing MySQL client...\033[0m"
    if ! command -v mysql &>/dev/null; then
        sudo apt install -y mysql-client || {
            echo -e "\e[91mError: Failed to install MySQL client. Please install it manually and try again.\033[0m"
            exit 1
        }
        echo -e "\e[92mMySQL client installed successfully.\033[0m"
    else
        echo -e "\e[92mMySQL client is already installed.\033[0m"
    fi

    sudo apt install -y software-properties-common || {
        echo -e "\e[91mError: Failed to install software-properties-common.\033[0m"
        exit 1
    }
    sudo add-apt-repository -y ppa:ondrej/php || {
        echo -e "\e[91mError: Failed to add PPA ondrej/php. Trying with locale override...\033[0m"
        sudo LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php || {
            echo -e "\e[91mError: Failed to add PPA even with locale override.\033[0m"
            exit 1
        }
    }
    sudo apt update || {
        echo -e "\e[91mError: Failed to update package list after adding PPA.\033[0m"
        exit 1
    }

    sudo apt install -y git unzip curl wget jq || {
        echo -e "\e[91mError: Failed to install basic tools.\033[0m"
        exit 1
    }

    if ! dpkg -s apache2 &>/dev/null; then
        sudo apt install -y apache2 || {
            echo -e "\e[91mError: Failed to install Apache2.\033[0m"
            exit 1
        }
    fi

    DEBIAN_FRONTEND=noninteractive sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-zip php8.2-gd php8.2-curl php8.2-soap php8.2-ssh2 libssh2-1-dev libssh2-1 php8.2-pdo || {
        echo -e "\e[91mError: Failed to install PHP 8.2 and modules.\033[0m"
        exit 1
    }

    sudo apt install -y libapache2-mod-php8.2 || {
        echo -e "\e[91mError: Failed to install libapache2-mod-php8.2.\033[0m"
        exit 1
    }

    sudo apt install -y python3-certbot-apache || {
        echo -e "\e[91mError: Failed to install Certbot for Apache.\033[0m"
        exit 1
    }
    sudo systemctl enable certbot.timer || {
        echo -e "\e[91mError: Failed to enable certbot timer.\033[0m"
        exit 1
    }

    if ! dpkg -s ufw &>/dev/null; then
        sudo apt install -y ufw || {
            echo -e "\e[91mError: Failed to install UFW.\033[0m"
            exit 1
        }
    fi

    ENV_FILE="/opt/marzban/.env"
    if [ ! -f "$ENV_FILE" ]; then
        echo -e "\e[91mError: Marzban .env file not found. Cannot proceed without Marzban configuration.\033[0m"
        exit 1
    fi

    MYSQL_ROOT_PASSWORD=$(grep "MYSQL_ROOT_PASSWORD=" "$ENV_FILE" | cut -d'=' -f2 | tr -d '[:space:]' | sed 's/"//g')
    ROOT_USER="root"

    if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
        echo -e "\e[93mWarning: Could not retrieve MySQL root password from Marzban .env file.\033[0m"
        read -s -p "Please enter the MySQL root password manually: " MYSQL_ROOT_PASSWORD
        echo
    fi

    MYSQL_CONTAINER=$(docker ps -q --filter "name=mysql" --no-trunc)
    if [ -z "$MYSQL_CONTAINER" ]; then
        echo -e "\e[91mError: Could not find a running MySQL container. Ensure Marzban is running with Docker.\033[0m"
        echo -e "\e[93mRunning containers:\033[0m"
        docker ps
        exit 1
    fi

    echo "Testing MySQL connection..."

    if [ -f "/opt/marzban/.env" ]; then
        MYSQL_ROOT_PASSWORD=$(grep -E '^MYSQL_ROOT_PASSWORD=' /opt/marzban/.env | cut -d '=' -f2- | tr -d '" \n\r')
        if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
            echo -e "\e[93mWarning: MYSQL_ROOT_PASSWORD not found in .env. Please enter it manually.\033[0m"
            read -s -p "Enter MySQL root password: " MYSQL_ROOT_PASSWORD
            echo
        fi
    else
        echo -e "\e[93mWarning: .env file not found. Please enter MySQL root password manually.\033[0m"
        read -s -p "Enter MySQL root password: " MYSQL_ROOT_PASSWORD
        echo
    fi

    ROOT_USER="root"
    echo -e "\e[32mUsing MySQL container: $(docker inspect -f '{{.Name}}' "$MYSQL_CONTAINER" | cut -c2-)\033[0m"

    mysql -u "$ROOT_USER" -p"$MYSQL_ROOT_PASSWORD" -h 127.0.0.1 -P 3306 -e "SELECT 1;" 2>/tmp/mysql_error.log
    if [ $? -eq 0 ]; then
        echo -e "\e[92mMySQL connection successful (direct host method).\033[0m"
    else
        if [ -n "$MYSQL_CONTAINER" ]; then
            echo -e "\e[93mDirect connection failed, trying inside container...\033[0m"
            docker exec "$MYSQL_CONTAINER" bash -c "echo 'SELECT 1;' | mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD'" 2>/tmp/mysql_error.log
            if [ $? -eq 0 ]; then
                echo -e "\e[92mMySQL connection successful (container method).\033[0m"
            else
                echo -e "\e[91mError: Failed to connect to MySQL using both methods.\033[0m"
                echo -e "\e[93mPassword used: '$MYSQL_ROOT_PASSWORD'\033[0m"
                echo -e "\e[93mError details:\033[0m"
                cat /tmp/mysql_error.log
                echo -e "\e[93mPlease ensure MySQL is running and the root password is correct.\033[0m"
                read -s -p "Enter the correct MySQL root password: " NEW_PASSWORD
                echo
                MYSQL_ROOT_PASSWORD="$NEW_PASSWORD"
                mysql -u "$ROOT_USER" -p"$MYSQL_ROOT_PASSWORD" -h 127.0.0.1 -P 3306 -e "SELECT 1;" 2>/tmp/mysql_error.log || {
                    docker exec "$MYSQL_CONTAINER" bash -c "echo 'SELECT 1;' | mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD'" 2>/tmp/mysql_error.log || {
                        echo -e "\e[91mError: Still can't connect with new password.\033[0m"
                        echo -e "\e[93mError details:\033[0m"
                        cat /tmp/mysql_error.log
                        exit 1
                    }
                }
                echo -e "\e[92mMySQL connection successful with new password.\033[0m"
            fi
        else
            echo -e "\e[91mError: No MySQL container found and direct connection failed.\033[0m"
            echo -e "\e[93mPassword used: '$MYSQL_ROOT_PASSWORD'\033[0m"
            echo -e "\e[93mError details:\033[0m"
            cat /tmp/mysql_error.log
            exit 1
        fi
    fi

    clear
    echo -e "\e[33mConfiguring Mirza Bot database credentials...\033[0m"
    default_dbuser=$(openssl rand -base64 12 | tr -dc 'a-zA-Z' | head -c8)
    printf "\e[33m[+] \e[36mDatabase username (default: $default_dbuser): \033[0m"
    read dbuser
    if [ -z "$dbuser" ]; then
        dbuser="$default_dbuser"
    fi

    default_dbpass=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | head -c12)
    printf "\e[33m[+] \e[36mDatabase password (default: $default_dbpass): \033[0m"
    read -s dbpass
    echo
    if [ -z "$dbpass" ]; then
        dbpass="$default_dbpass"
    fi
    dbname="mirzabot"

    docker exec "$MYSQL_CONTAINER" bash -c "mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD' -e \"CREATE DATABASE IF NOT EXISTS $dbname; CREATE USER IF NOT EXISTS '$dbuser'@'%' IDENTIFIED BY '$dbpass'; GRANT ALL PRIVILEGES ON $dbname.* TO '$dbuser'@'%'; FLUSH PRIVILEGES;\"" || {
        echo -e "\e[91mError: Failed to create database or user in Marzban MySQL container.\033[0m"
        exit 1
    }
    echo -e "\e[92mDatabase '$dbname' created successfully.\033[0m"

    BOT_DIR="/var/www/html/mirzabotconfig"
    if [ -d "$BOT_DIR" ]; then
        echo -e "\e[93mDirectory $BOT_DIR already exists. Removing...\033[0m"
        sudo rm -rf "$BOT_DIR" || {
            echo -e "\e[91mError: Failed to remove existing directory $BOT_DIR.\033[0m"
            exit 1
        }
    fi
    sudo mkdir -p "$BOT_DIR" || {
        echo -e "\e[91mError: Failed to create directory $BOT_DIR.\033[0m"
        exit 1
    }

    ZIP_URL=$(curl -s https://api.github.com/repos/Mmd-Amir/mirza_pro/releases/latest | grep "zipball_url" | cut -d '"' -f 4)
    if [[ "$1" == "-v" && "$2" == "beta" ]] || [[ "$1" == "-beta" ]] || [[ "$1" == "-" && "$2" == "beta" ]]; then
        ZIP_URL="https://github.com/Mmd-Amir/mirza_pro/archive/refs/heads/main.zip"
    elif [[ "$1" == "-v" && -n "$2" ]]; then
        ZIP_URL="https://github.com/Mmd-Amir/mirza_pro/archive/refs/tags/$2.zip"
    fi

    TEMP_DIR="/tmp/mirzabot"
    mkdir -p "$TEMP_DIR"
    wget -O "$TEMP_DIR/bot.zip" "$ZIP_URL" || {
        echo -e "\e[91mError: Failed to download bot files.\033[0m"
        exit 1
    }
    unzip "$TEMP_DIR/bot.zip" -d "$TEMP_DIR" || {
        echo -e "\e[91mError: Failed to unzip bot files.\033[0m"
        exit 1
    }
    EXTRACTED_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d)
    mv "$EXTRACTED_DIR"/* "$BOT_DIR" || {
        echo -e "\e[91mError: Failed to move bot files.\033[0m"
        exit 1
    }
    rm -rf "$TEMP_DIR"

    sudo chown -R www-data:www-data "$BOT_DIR"
    sudo chmod -R 755 "$BOT_DIR"
    echo -e "\e[92mBot files installed in $BOT_DIR.\033[0m"
    sleep 3
    clear

    echo -e "\e[32mConfiguring Apache ports...\033[0m"
    sudo bash -c "echo -n > /etc/apache2/ports.conf"  
    cat <<EOF | sudo tee /etc/apache2/ports.conf

Listen 80
Listen 88

EOF
    if [ $? -ne 0 ]; then
        echo -e "\e[91mError: Failed to configure ports.conf.\033[0m"
        exit 1
    fi

    sudo bash -c "echo -n > /etc/apache2/sites-available/000-default.conf"  
    cat <<EOF | sudo tee /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>

EOF
    if [ $? -ne 0 ]; then
        echo -e "\e[91mError: Failed to configure 000-default.conf.\033[0m"
        exit 1
    fi

    sudo systemctl enable apache2 || {
        echo -e "\e[91mError: Failed to enable Apache2.\033[0m"
        exit 1
    }
    sudo systemctl restart apache2 || {
        echo -e "\e[91mError: Failed to restart Apache2.\033[0m"
        exit 1
    }

    echo -e "\e[32mConfiguring SSL on port 88...\033[0m\n"
    sudo ufw allow 80 || {
        echo -e "\e[91mError: Failed to configure firewall for port 80.\033[0m"
        exit 1
    }
    sudo ufw allow 88 || {
        echo -e "\e[91mError: Failed to configure firewall for port 88.\033[0m"
        exit 1
    }
    clear
    printf "\e[33m[+] \e[36mEnter the domain (e.g., example.com): \033[0m"
    read domainname
    while [[ ! "$domainname" =~ ^[a-zA-Z0-9.-]+$ ]]; do
        echo -e "\e[91mInvalid domain format. Must be like 'example.com'. Please try again.\033[0m"
        printf "\e[33m[+] \e[36mEnter the domain (e.g., example.com): \033[0m"
        read domainname
    done
    DOMAIN_NAME="$domainname"
    echo -e "\e[92mDomain set to: $DOMAIN_NAME\033[0m"

    sudo systemctl restart apache2 || {
        echo -e "\e[91mError: Failed to restart Apache2 before Certbot.\033[0m"
        exit 1
    }
    if ! wait_for_certbot; then
        echo -e "\e[91mError: Certbot is busy. Please try again shortly.\033[0m"
        exit 1
    fi
    sudo certbot --apache --agree-tos --preferred-challenges http -d "$DOMAIN_NAME" --https-port 88 --no-redirect || {
        echo -e "\e[91mError: Failed to configure SSL with Certbot on port 88.\033[0m"
        exit 1
    }

    sudo bash -c "echo -n > /etc/apache2/sites-available/000-default-le-ssl.conf"  
    cat <<EOF | sudo tee /etc/apache2/sites-available/000-default-le-ssl.conf
<IfModule mod_ssl.c>
<VirtualHost *:88>
    ServerAdmin webmaster@localhost
    ServerName $DOMAIN_NAME
    DocumentRoot /var/www/html
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN_NAME/privkey.pem
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5
</VirtualHost>
</IfModule>
EOF
    if [ $? -ne 0 ]; then
        echo -e "\e[91mError: Failed to create SSL VirtualHost configuration.\033[0m"
        exit 1
    fi
    sudo a2enmod ssl || {
        echo -e "\e[91mError: Failed to enable SSL module.\033[0m"
        exit 1
    }
    sudo a2ensite 000-default-le-ssl.conf || {
        echo -e "\e[91mError: Failed to enable SSL site.\033[0m"
        exit 1
    }
    sudo bash -c "echo -n > /etc/apache2/ports.conf"
    cat <<EOF | sudo tee /etc/apache2/ports.conf
Listen 88
EOF
    sudo apache2ctl configtest || {
        echo -e "\e[91mError: Apache configuration test failed after Certbot.\033[0m"
        exit 1
    }
    sudo systemctl restart apache2 || {
        echo -e "\e[91mError: Failed to restart Apache2 after SSL configuration.\033[0m"
        systemctl status apache2.service
        exit 1
    }

    echo -e "\e[32mDisabling port 80 as it's no longer needed...\033[0m"
    sudo a2dissite 000-default.conf || {
        echo -e "\e[91mError: Failed to disable port 80 VirtualHost.\033[0m"
        exit 1
    }
    sudo ufw delete allow 80 || {
        echo -e "\e[91mError: Failed to remove port 80 from firewall.\033[0m"
        exit 1
    }
    sudo apache2ctl configtest || {
        echo -e "\e[91mError: Apache configuration test failed.\033[0m"
        exit 1
    }
    sudo systemctl restart apache2 || {
        echo -e "\e[91mError: Failed to restart Apache2 after disabling port 80.\033[0m"
        systemctl status apache2.service
        exit 1
    }
    echo -e "\e[92mSSL configured successfully on port 88. Port 80 disabled.\033[0m"
    sleep 3
    clear

    printf "\e[33m[+] \e[36mBot Token: \033[0m"
    read YOUR_BOT_TOKEN
    while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; do
        echo -e "\e[91mInvalid bot token format. Please try again.\033[0m"
        printf "\e[33m[+] \e[36mBot Token: \033[0m"
        read YOUR_BOT_TOKEN
    done

    printf "\e[33m[+] \e[36mChat id: \033[0m"
    read YOUR_CHAT_ID
    while [[ ! "$YOUR_CHAT_ID" =~ ^-?[0-9]+$ ]]; do
        echo -e "\e[91mInvalid chat ID format. Please try again.\033[0m"
        printf "\e[33m[+] \e[36mChat id: \033[0m"
        read YOUR_CHAT_ID
    done

    YOUR_DOMAIN="$DOMAIN_NAME:88"  
    printf "\e[33m[+] \e[36mUsernamebot: \033[0m"
    read YOUR_BOTNAME
    while [ -z "$YOUR_BOTNAME" ]; do
        echo -e "\e[91mError: Bot username cannot be empty.\033[0m"
        printf "\e[33m[+] \e[36mUsernamebot: \033[0m"
        read YOUR_BOTNAME
    done

    ASAS="$"
    secrettoken=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    cat <<EOF > "$BOT_DIR/config.php"
<?php
${ASAS}APIKEY = '$YOUR_BOT_TOKEN';
${ASAS}usernamedb = '$dbuser';
${ASAS}passworddb = '$dbpass';
${ASAS}dbname = '$dbname';
${ASAS}domainhosts = '$YOUR_DOMAIN';
${ASAS}adminnumber = '$YOUR_CHAT_ID';
${ASAS}usernamebot = '$YOUR_BOTNAME';
${ASAS}secrettoken = '$secrettoken';

${ASAS}connect = mysqli_connect('127.0.0.1', \$usernamedb, \$passworddb, \$dbname);
if (${ASAS}connect->connect_error) {
    die('Database connection failed: ' . ${ASAS}connect->connect_error);
}
mysqli_set_charset(${ASAS}connect, 'utf8mb4');

${ASAS}options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
${ASAS}dsn = "mysql:host=127.0.0.1;port=3306;dbname=\$dbname;charset=utf8mb4";
try {
    ${ASAS}pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options);
} catch (\PDOException \$e) {
    die('PDO Connection failed: ' . \$e->getMessage());
}
?>
EOF

    curl -F "url=https://${YOUR_DOMAIN}/mirzabotconfig/index.php" \
         -F "secret_token=${secrettoken}" \
         "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook" || {
        echo -e "\e[91mError: Failed to set webhook.\033[0m"
        exit 1
    }

    MESSAGE="✅ The bot is installed! for start bot send comment /start"
    curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="$MESSAGE" || {
        echo -e "\033[31mError: Failed to send message to Telegram.\033[0m"
        return 1
    }

    TABLE_SETUP_URL="https://${YOUR_DOMAIN}/mirzabotconfig/table.php"
    echo -e "\033[33mSetting up database tables...\033[0m"
    curl $TABLE_SETUP_URL || {
        echo -e "\033[31mError: Failed to execute table creation script at $TABLE_SETUP_URL.\033[0m"
        return 1
    }

    echo -e "\033[32mBot installed successfully!\033[0m"
    echo -e "\033[102mDomain Bot: https://$DOMAIN_NAME\033[0m"
    echo -e "\033[104mDatabase address: https://$DOMAIN_NAME/phpmyadmin\033[0m"
    echo -e "\033[33mDatabase name: \033[36m$DB_NAME\033[0m"
    echo -e "\033[33mDatabase username: \033[36m$DB_USERNAME\033[0m"
    echo -e "\033[33mDatabase password: \033[36m$DB_PASSWORD\033[0m"

    chmod +x /root/install.sh
    ln -sf /root/install.sh /usr/local/bin/mirza >/dev/null 2>&1
}

function update_bot() {
    echo "Updating Mirza Bot..."

    if ! sudo apt update && sudo apt upgrade -y; then
        echo -e "\e[91mError updating the server. Exiting...\033[0m"
        exit 1
    fi
    echo -e "\e[92mServer packages updated successfully...\033[0m\n"

    BOT_DIR="/var/www/html/mirzabotconfig"
    if [ ! -d "$BOT_DIR" ]; then
        echo -e "\e[91mError: Mirza Bot is not installed. Please install it first.\033[0m"
        exit 1
    fi

    if [[ "$1" == "-beta" ]] || [[ "$1" == "-v" && "$2" == "beta" ]]; then
        ZIP_URL="https://github.com/Mmd-Amir/mirza_pro/archive/refs/heads/main.zip"
    else
        ZIP_URL=$(curl -s https://api.github.com/repos/Mmd-Amir/mirza_pro/releases/latest | grep "zipball_url" | cut -d '"' -f4)
    fi

    TEMP_DIR="/tmp/mirzabot_update"
    mkdir -p "$TEMP_DIR"

    wget -O "$TEMP_DIR/bot.zip" "$ZIP_URL" || {
        echo -e "\e[91mError: Failed to download update package.\033[0m"
        exit 1
    }
    unzip "$TEMP_DIR/bot.zip" -d "$TEMP_DIR"

    EXTRACTED_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d)

    CONFIG_PATH="/var/www/html/mirzabotconfig/config.php"
    TEMP_CONFIG="/root/mirza_config_backup.php"
    if [ -f "$CONFIG_PATH" ]; then
        cp "$CONFIG_PATH" "$TEMP_CONFIG" || {
            echo -e "\e[91mConfig file backup failed!\033[0m"
            exit 1
        }
    fi

    sudo rm -rf /var/www/html/mirzabotconfig || {
        echo -e "\e[91mFailed to remove old bot files!\033[0m"
        exit 1
    }

    sudo mkdir -p /var/www/html/mirzabotconfig
    sudo mv "$EXTRACTED_DIR"/* /var/www/html/mirzabotconfig/ || {
        echo -e "\e[91mFile transfer failed!\033[0m"
        exit 1
    }

    if [ -f "$TEMP_CONFIG" ]; then
        sudo mv "$TEMP_CONFIG" "$CONFIG_PATH" || {
            echo -e "\e[91mConfig file restore failed!\033[0m"
            exit 1
        }
    fi

    INSTALL_SCRIPT_PATH=$(find /var/www/html/mirzabotconfig -maxdepth 2 -name "install.sh" -print -quit)
    if [ -n "$INSTALL_SCRIPT_PATH" ]; then
        sudo cp "$INSTALL_SCRIPT_PATH" /root/install.sh
        echo -e "\n\e[92mCopied latest install.sh to /root/install.sh.\033[0m"
    else
        RAW_INSTALL_URL="https://raw.githubusercontent.com/Mmd-Amir/mirza_pro/main/install.sh"
        if curl -fsSL "$RAW_INSTALL_URL" -o /root/install.sh; then
            echo -e "\n\e[92mFetched install.sh from upstream repository.\033[0m"
        else
            echo -e "\n\e[91mWarning: install.sh not found locally and download failed. Existing /root/install.sh left untouched.\033[0m"
        fi
    fi
    sudo chown -R www-data:www-data /var/www/html/mirzabotconfig/
    sudo chmod -R 755 /var/www/html/mirzabotconfig/

    URL=$(grep -oP "\$domainhosts\s*=\s*[\'\"]\K[^\'\"]+" "$CONFIG_PATH" 2>/dev/null | head -1)
    if [ -z "$URL" ]; then
        URL=$(grep "domainhosts" "$CONFIG_PATH" | sed -n "s/.*domainhosts.*=.*[\'\"]\([^\'\"]*\)[\'\"].*/\1/p" | head -1)
    fi
    if [ -n "$URL" ]; then
        CLEAN_URL=${URL#http://}
        CLEAN_URL=${CLEAN_URL#https://}
        CLEAN_URL=${CLEAN_URL%/}
        curl -s "https://${CLEAN_URL}/table.php" || {
            echo -e "\e[91mSetup script execution failed for https://${CLEAN_URL}/table.php!\033[0m"
        }
    else
        echo -e "\e[91mUnable to detect domainhosts from config.php. Skipping table setup call.\033[0m"
    fi

    echo -e "\e[33m[VERIFY] Checking if database tables are created...\033[0m"

    DB_USERNAME=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASSWORD=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_NAME=$(grep '^\$dbname' "$CONFIG_PATH" | awk -F"'" '{print $2}')

    if [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ] || [ -z "$DB_NAME" ]; then
        echo -e "\e[91m[ERROR] Failed to read database credentials from config.php. Cannot verify tables.\033[0m"
    else
        TABLES=$(mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -D "$DB_NAME" -e "SHOW TABLES LIKE 'setting';" 2>&1)

        if echo "$TABLES" | grep -q "setting"; then
            echo -e "\e[92m[SUCCESS] Database table 'setting' exists!\033[0m"
        else
            echo -e "\e[91m[ERROR] Database table 'setting' NOT FOUND!\033[0m"
            echo -e "\e[91mPlease check /var/log/mirzabot_setup.log for details\033[0m"
        fi
    fi

    rm -rf "$TEMP_DIR"

    echo -e "\n\e[92mMirza Bot updated to latest version successfully!\033[0m"

    if [ -f "/root/install.sh" ]; then
        sudo chmod +x /root/install.sh
        sudo ln -sf /root/install.sh /usr/local/bin/mirza >/dev/null 2>&1
        echo -e "\e[92mEnsured /root/install.sh is executable and 'mirza' command is linked.\033[0m"
    else
        echo -e "\e[91mError: /root/install.sh not found after update attempt. Cannot make it executable or link 'mirza' command.\033[0m"
    fi
}

function remove_bot() {
    echo -e "\e[33mStarting Mirza Bot removal process...\033[0m"
    LOG_FILE="/var/log/remove_bot.log"
    echo "Log file: $LOG_FILE" > "$LOG_FILE"

    BOT_DIR="/var/www/html/mirzabotconfig"
    if [ ! -d "$BOT_DIR" ]; then
        echo -e "\e[31m[ERROR]\033[0m Mirza Bot is not installed (/var/www/html/mirzabotconfig not found)." | tee -a "$LOG_FILE"
        echo -e "\e[33mNothing to remove. Exiting...\033[0m" | tee -a "$LOG_FILE"
        sleep 2
        exit 1
    fi

    read -p "Are you sure you want to remove Mirza Bot and its dependencies? (y/n): " choice
    if [[ "$choice" != "y" ]]; then
        echo "Aborting..." | tee -a "$LOG_FILE"
        exit 0
    fi

    if check_marzban_installed; then
        echo -e "\e[41m[IMPORTANT NOTICE]\033[0m \e[33mMarzban detected. Proceeding with Marzban-compatible removal.\033[0m" | tee -a "$LOG_FILE"
        remove_bot_with_marzban
        return 0
    fi

    echo "Removing Mirza Bot..." | tee -a "$LOG_FILE"

    if [ -d "$BOT_DIR" ]; then
        sudo rm -rf "$BOT_DIR" && echo -e "\e[92mBot directory removed: $BOT_DIR\033[0m" | tee -a "$LOG_FILE" || {
            echo -e "\e[91mFailed to remove bot directory: $BOT_DIR. Exiting...\033[0m" | tee -a "$LOG_FILE"
            exit 1
        }
    fi

    CONFIG_PATH="/root/config.php"
    if [ -f "$CONFIG_PATH" ]; then
        sudo shred -u -n 5 "$CONFIG_PATH" && echo -e "\e[92mConfig file securely removed: $CONFIG_PATH\033[0m" | tee -a "$LOG_FILE" || {
            echo -e "\e[91mFailed to securely remove config file: $CONFIG_PATH\033[0m" | tee -a "$LOG_FILE"
        }
    fi

    echo -e "\e[33mRemoving MySQL and database...\033[0m" | tee -a "$LOG_FILE"
    sudo systemctl stop mysql
    sudo systemctl disable mysql
    sudo systemctl daemon-reload

    sudo apt --fix-broken install -y

    sudo apt-get purge -y mysql-server mysql-client mysql-common mysql-server-core-* mysql-client-core-*
    sudo rm -rf /etc/mysql /var/lib/mysql /var/log/mysql /var/log/mysql.* /usr/lib/mysql /usr/include/mysql /usr/share/mysql
    sudo rm /lib/systemd/system/mysql.service
    sudo rm /etc/init.d/mysql

    sudo dpkg --remove --force-remove-reinstreq mysql-server mysql-server-8.0

    sudo find /etc/systemd /lib/systemd /usr/lib/systemd -name "*mysql*" -exec rm -f {} \;

    sudo apt-get purge -y mysql-server mysql-server-8.0 mysql-client mysql-client-8.0
    sudo apt-get purge -y mysql-client-core-8.0 mysql-server-core-8.0 mysql-common php-mysql php8.2-mysql php8.2-mysql php-mariadb-mysql-kbs

    sudo apt-get autoremove --purge -y
    sudo apt-get clean
    sudo apt-get update

    echo -e "\e[92mMySQL has been completely removed.\033[0m" | tee -a "$LOG_FILE"

    echo -e "\e[33mRemoving PHPMyAdmin...\033[0m" | tee -a "$LOG_FILE"
    if dpkg -s phpmyadmin &>/dev/null; then
        sudo apt-get purge -y phpmyadmin && echo -e "\e[92mPHPMyAdmin removed.\033[0m" | tee -a "$LOG_FILE"
        sudo apt-get autoremove -y && sudo apt-get autoclean -y
    else
        echo -e "\e[93mPHPMyAdmin is not installed.\033[0m" | tee -a "$LOG_FILE"
    fi

    echo -e "\e[33mRemoving Apache...\033[0m" | tee -a "$LOG_FILE"
    sudo systemctl stop apache2 || {
        echo -e "\e[91mFailed to stop Apache. Continuing anyway...\033[0m" | tee -a "$LOG_FILE"
    }
    sudo systemctl disable apache2 || {
        echo -e "\e[91mFailed to disable Apache. Continuing anyway...\033[0m" | tee -a "$LOG_FILE"
    }
    sudo apt-get purge -y apache2 apache2-utils apache2-bin apache2-data libapache2-mod-php* || {
        echo -e "\e[91mFailed to purge Apache packages.\033[0m" | tee -a "$LOG_FILE"
    }
    sudo apt-get autoremove --purge -y
    sudo apt-get autoclean -y
    sudo rm -rf /etc/apache2 /var/www/html/mirzabotconfig

    echo -e "\e[33mRemoving Apache and PHP configurations...\033[0m" | tee -a "$LOG_FILE"
    sudo a2disconf phpmyadmin.conf &>/dev/null
    sudo rm -f /etc/apache2/conf-available/phpmyadmin.conf
    sudo systemctl restart apache2

    echo -e "\e[33mRemoving additional packages...\033[0m" | tee -a "$LOG_FILE"
    sudo apt-get remove -y php-soap php-ssh2 libssh2-1-dev libssh2-1 \
        && echo -e "\e[92mRemoved additional PHP packages.\033[0m" | tee -a "$LOG_FILE" || echo -e "\e[93mSome additional PHP packages may not be installed.\033[0m" | tee -a "$LOG_FILE"

    echo -e "\e[33mResetting firewall rules (except SSL)...\033[0m" | tee -a "$LOG_FILE"
    sudo ufw delete allow 'Apache'
    sudo ufw reload

    echo -e "\e[92mMirza Bot, MySQL, and their dependencies have been completely removed.\033[0m" | tee -a "$LOG_FILE"
}

function remove_bot_with_marzban() {
    echo -e "\e[33mRemoving Mirza Bot alongside Marzban...\033[0m" | tee -a "$LOG_FILE"

    BOT_DIR="/var/www/html/mirzabotconfig"

    if [ ! -d "$BOT_DIR" ]; then
        echo -e "\e[93mWarning: Bot directory $BOT_DIR not found. Assuming it was already removed.\033[0m" | tee -a "$LOG_FILE"
        DB_NAME="mirzabot"  
        DB_USER=""
    else
        CONFIG_PATH="$BOT_DIR/config.php"
        if [ -f "$CONFIG_PATH" ]; then
            DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
            DB_NAME=$(grep '^\$dbname' "$CONFIG_PATH" | awk -F"'" '{print $2}')
            if [ -z "$DB_USER" ] || [ -z "$DB_NAME" ]; then
                echo -e "\e[91mError: Could not extract database credentials from $CONFIG_PATH. Using defaults.\033[0m" | tee -a "$LOG_FILE"
                DB_NAME="mirzabot"  
                DB_USER=""
            else
                echo -e "\e[92mFound database credentials: User=$DB_USER, Database=$DB_NAME\033[0m" | tee -a "$LOG_FILE"
            fi
        else
            echo -e "\e[93mWarning: config.php not found at $CONFIG_PATH. Assuming default database name 'mirzabot'.\033[0m" | tee -a "$LOG_FILE"
            DB_NAME="mirzabot"
            DB_USER=""
        fi

        sudo rm -rf "$BOT_DIR" && echo -e "\e[92mBot directory removed: $BOT_DIR\033[0m" | tee -a "$LOG_FILE" || {
            echo -e "\e[91mFailed to remove bot directory: $BOT_DIR. Exiting...\033[0m" | tee -a "$LOG_FILE"
            exit 1
        }
    fi

    ENV_FILE="/opt/marzban/.env"
    if [ -f "$ENV_FILE" ]; then
        MYSQL_ROOT_PASSWORD=$(grep "MYSQL_ROOT_PASSWORD=" "$ENV_FILE" | cut -d'=' -f2 | tr -d '[:space:]' | sed 's/"//g')
        ROOT_USER="root"
    else
        echo -e "\e[91mError: Marzban .env file not found. Cannot proceed without MySQL root password.\033[0m" | tee -a "$LOG_FILE"
        exit 1
    fi

    MYSQL_CONTAINER=$(docker ps -q --filter "name=mysql" --no-trunc)
    if [ -z "$MYSQL_CONTAINER" ]; then
        echo -e "\e[91mError: Could not find a running MySQL container. Ensure Marzban is running.\033[0m" | tee -a "$LOG_FILE"
        exit 1
    fi

    if [ -n "$DB_NAME" ]; then
        echo -e "\e[33mRemoving database $DB_NAME...\033[0m" | tee -a "$LOG_FILE"
        docker exec "$MYSQL_CONTAINER" bash -c "mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD' -e \"DROP DATABASE IF EXISTS $DB_NAME;\"" && {
            echo -e "\e[92mDatabase $DB_NAME removed successfully.\033[0m" | tee -a "$LOG_FILE"
        } || {
            echo -e "\e[91mFailed to remove database $DB_NAME.\033[0m" | tee -a "$LOG_FILE"
        }
    fi

    if [ -n "$DB_USER" ]; then
        echo -e "\e[33mRemoving database user $DB_USER...\033[0m" | tee -a "$LOG_FILE"
        docker exec "$MYSQL_CONTAINER" bash -c "mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD' -e \"DROP USER IF EXISTS '$DB_USER'@'%'; FLUSH PRIVILEGES;\"" && {
            echo -e "\e[92mUser $DB_USER removed successfully.\033[0m" | tee -a "$LOG_FILE"
        } || {
            echo -e "\e[91mFailed to remove user $DB_USER.\033[0m" | tee -a "$LOG_FILE"
        }
    else
        echo -e "\e[93mWarning: No database user specified. Checking for non-default users...\033[0m" | tee -a "$LOG_FILE"
        MIRZA_USERS=$(docker exec "$MYSQL_CONTAINER" bash -c "mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD' -e \"SELECT User FROM mysql.user WHERE User NOT IN ('root', 'mysql.infoschema', 'mysql.session', 'mysql.sys', 'marzban');\"" | grep -v "User" | awk '{print $1}')
        if [ -n "$MIRZA_USERS" ]; then
            for user in $MIRZA_USERS; do
                echo -e "\e[33mRemoving detected non-default user: $user...\033[0m" | tee -a "$LOG_FILE"
                docker exec "$MYSQL_CONTAINER" bash -c "mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD' -e \"DROP USER IF EXISTS '$user'@'%'; FLUSH PRIVILEGES;\"" && {
                    echo -e "\e[92mUser $user removed successfully.\033[0m" | tee -a "$LOG_FILE"
                } || {
                    echo -e "\e[91mFailed to remove user $user.\033[0m" | tee -a "$LOG_FILE"
                }
            done
        else
            echo -e "\e[93mNo non-default users found.\033[0m" | tee -a "$LOG_FILE"
        fi
    fi

    echo -e "\e[33mRemoving Apache...\033[0m" | tee -a "$LOG_FILE"
    sudo systemctl stop apache2 || {
        echo -e "\e[91mFailed to stop Apache. Continuing anyway...\033[0m" | tee -a "$LOG_FILE"
    }
    sudo systemctl disable apache2 || {
        echo -e "\e[91mFailed to disable Apache. Continuing anyway...\033[0m" | tee -a "$LOG_FILE"
    }
    sudo apt-get purge -y apache2 apache2-utils apache2-bin apache2-data libapache2-mod-php* || {
        echo -e "\e[91mFailed to purge Apache packages.\033[0m" | tee -a "$LOG_FILE"
    }
    sudo apt-get autoremove --purge -y
    sudo apt-get autoclean -y
    sudo rm -rf /etc/apache2 /var/www/html/mirzabotconfig

    echo -e "\e[33mResetting firewall rules (keeping SSL)...\033[0m" | tee -a "$LOG_FILE"
    sudo ufw delete allow 'Apache' || {
        echo -e "\e[91mFailed to remove Apache rule from UFW.\033[0m" | tee -a "$LOG_FILE"
    }
    sudo ufw reload

    echo -e "\e[92mMirza Bot has been removed alongside Marzban. SSL certificates remain intact.\033[0m" | tee -a "$LOG_FILE"
}

function extract_db_credentials() {
    CONFIG_PATH="/var/www/html/mirzabotconfig/config.php"
    if [ -f "$CONFIG_PATH" ]; then
        DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
        DB_PASS=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
        DB_NAME=$(grep '^\$dbname' "$CONFIG_PATH" | awk -F"'" '{print $2}')
        TELEGRAM_TOKEN=$(grep '^\$APIKEY' "$CONFIG_PATH" | awk -F"'" '{print $2}')
        TELEGRAM_CHAT_ID=$(grep '^\$adminnumber' "$CONFIG_PATH" | awk -F"'" '{print $2}')
        if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ] || [ -z "$TELEGRAM_TOKEN" ] || [ -z "$TELEGRAM_CHAT_ID" ]; then
            echo -e "\033[31m[ERROR]\033[0m Failed to extract required credentials from $CONFIG_PATH."
            return 1
        fi
        return 0
    else
        echo -e "\033[31m[ERROR]\033[0m config.php not found at $CONFIG_PATH."
        return 1
    fi
}

function translate_cron() {
    local cron_line="$1"
    local schedule=""
    case "$cron_line" in
        "* * * * *"*) schedule="Every Minute" ;;
        "0 * * * *"*) schedule="Every Hour" ;;
        "0 0 * * *"*) schedule="Every Day" ;;
        "0 0 * * 0"*) schedule="Every Week" ;;
        *) schedule="Custom Schedule ($cron_line)" ;;
    esac
    echo "$schedule"
}

function export_database() {
    echo -e "\033[33mChecking database configuration...\033[0m"

    if ! extract_db_credentials; then
        return 1
    fi

    if check_marzban_installed; then
        echo -e "\033[31m[ERROR]\033[0m Exporting database is not supported when Marzban is installed due to database being managed by Docker."
        return 1
    fi

    echo -e "\033[33mVerifying database existence...\033[0m"

    if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
        echo -e "\033[31m[ERROR]\033[0m Database $DB_NAME does not exist or credentials are incorrect."
        return 1
    fi

    BACKUP_FILE="/root/${DB_NAME}_backup.sql"
    echo -e "\033[33mCreating backup at $BACKUP_FILE...\033[0m"

    if ! mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"; then
        echo -e "\033[31m[ERROR]\033[0m Failed to create database backup."
        return 1
    fi

    echo -e "\033[32mBackup successfully created at $BACKUP_FILE.\033[0m"
}
function import_database() {
    echo -e "\033[33mChecking database configuration...\033[0m"

    if ! extract_db_credentials; then
        return 1
    fi

    if check_marzban_installed; then
        echo -e "\033[31m[ERROR]\033[0m Importing database is not supported when Marzban is installed due to database being managed by Docker."
        return 1
    fi

    echo -e "\033[33mVerifying database existence...\033[0m"

    if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
        echo -e "\033[31m[ERROR]\033[0m Database $DB_NAME does not exist or credentials are incorrect."
        return 1
    fi

    while true; do
        read -p "Enter the path to the backup file [default: /root/${DB_NAME}_backup.sql]: " BACKUP_FILE
        BACKUP_FILE=${BACKUP_FILE:-/root/${DB_NAME}_backup.sql}

        if [[ -f "$BACKUP_FILE" && "$BACKUP_FILE" =~ \.sql$ ]]; then
            break
        else
            echo -e "\033[31m[ERROR]\033[0m Invalid file path or format. Please provide a valid .sql file."
        fi
    done

    echo -e "\033[33mImporting backup from $BACKUP_FILE...\033[0m"

    if ! mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"; then
        echo -e "\033[31m[ERROR]\033[0m Failed to import database from backup file."
        return 1
    fi

    echo -e "\033[32mDatabase successfully imported from $BACKUP_FILE.\033[0m"
}

function auto_backup() {
    echo -e "\033[36mConfigure Automated Backup\033[0m"

    BOT_DIR="/var/www/html/mirzabotconfig"
    if [ ! -d "$BOT_DIR" ]; then
        echo -e "\033[31m[ERROR]\033[0m Mirza Bot is not installed ($BOT_DIR not found)."
        echo -e "\033[33mExiting...\033[0m"
        sleep 2
        return 1
    fi

    if ! extract_db_credentials; then
        return 1
    fi

    if check_marzban_installed; then
        echo -e "\033[41m[NOTICE]\033[0m \033[33mMarzban detected. Using Marzban-compatible backup.\033[0m"
        BACKUP_SCRIPT="/root/backup_mirza_marzban.sh"
        MYSQL_CONTAINER=$(docker ps -q --filter "name=mysql" --no-trunc)
        if [ -z "$MYSQL_CONTAINER" ]; then
            echo -e "\033[31m[ERROR]\033[0m No running MySQL container found for Marzban."
            return 1
        fi
        cat <<EOF > "$BACKUP_SCRIPT"
BACKUP_FILE="/root/\${DB_NAME}_\$(date +\"%Y%m%d_%H%M%S\").sql"
if docker exec $MYSQL_CONTAINER mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "\$BACKUP_FILE"; then
    curl -F document=@"\$BACKUP_FILE" "https://api.telegram.org/bot$TELEGRAM_TOKEN/sendDocument" -F chat_id="$TELEGRAM_CHAT_ID"
    if [ \$? -eq 0 ]; then
        rm "\$BACKUP_FILE"
    fi
else
    echo -e "\033[31m[ERROR]\033[0m Failed to create Marzban database backup."
fi
EOF
    else
        echo -e "\033[33mUsing standard backup.\033[0m"
        BACKUP_SCRIPT="/root/mirza_backup.sh"
        if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
            echo -e "\033[31m[ERROR]\033[0m Database $DB_NAME does not exist or credentials are incorrect."
            return 1
        fi
        cat <<EOF > "$BACKUP_SCRIPT"
BACKUP_FILE="/root/\${DB_NAME}_\$(date +\"%Y%m%d_%H%M%S\").sql"
if mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "\$BACKUP_FILE"; then
    curl -F document=@"\$BACKUP_FILE" "https://api.telegram.org/bot$TELEGRAM_TOKEN/sendDocument" -F chat_id="$TELEGRAM_CHAT_ID"
    if [ \$? -eq 0 ]; then
        rm "\$BACKUP_FILE"
    fi
else
    echo -e "\033[31m[ERROR]\033[0m Failed to create database backup."
fi
EOF
    fi

    chmod +x "$BACKUP_SCRIPT"

    CURRENT_CRON=$(crontab -l 2>/dev/null | grep "$BACKUP_SCRIPT" | grep -v "^#")
    if [ -n "$CURRENT_CRON" ]; then
        SCHEDULE=$(translate_cron "$CURRENT_CRON")
        echo -e "\033[33mCurrent Backup Schedule:\033[0m $SCHEDULE"
    else
        echo -e "\033[33mNo active backup schedule found.\033[0m"
    fi

    echo -e "\033[36m1) Every Minute\033[0m"
    echo -e "\033[36m2) Every Hour\033[0m"
    echo -e "\033[36m3) Every Day\033[0m"
    echo -e "\033[36m4) Every Week\033[0m"
    echo -e "\033[36m5) Disable Backup\033[0m"
    echo -e "\033[36m6) Back to Menu\033[0m"
    echo ""
    read -p "Select an option [1-6]: " backup_option

    update_cron() {
        local cron_line="$1"
        if [ -n "$CURRENT_CRON" ]; then
            crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" | crontab - && {
                echo -e "\033[92mRemoved previous backup schedule.\033[0m"
            } || {
                echo -e "\033[31mFailed to remove existing cron.\033[0m"
            }
        fi
        if [ -n "$cron_line" ]; then
            (crontab -l 2>/dev/null; echo "$cron_line") | crontab - && {
                echo -e "\033[92mBackup scheduled: $(translate_cron "$cron_line")\033[0m"
                bash "$BACKUP_SCRIPT" &>/dev/null &
            } || {
                echo -e "\033[31mFailed to schedule backup.\033[0m"
            }
        fi
    }

    case $backup_option in
        1) update_cron "* * * * * bash $BACKUP_SCRIPT" ;;
        2) update_cron "0 * * * * bash $BACKUP_SCRIPT" ;;
        3) update_cron "0 0 * * * bash $BACKUP_SCRIPT" ;;
        4) update_cron "0 0 * * 0 bash $BACKUP_SCRIPT" ;;
        5)
            if [ -n "$CURRENT_CRON" ]; then
                crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" | crontab - && {
                    echo -e "\033[92mAutomated backup disabled.\033[0m"
                } || {
                    echo -e "\033[31mFailed to disable backup.\033[0m"
                }
            else
                echo -e "\033[93mNo backup schedule to disable.\033[0m"
            fi
            ;;
        6) show_menu ;;
        *)
            echo -e "\033[31mInvalid option. Please try again.\033[0m"
            auto_backup
            ;;
    esac
}

function renew_ssl() {
    echo -e "\033[33mStarting SSL renewal process...\033[0m"

    if ! command -v certbot &>/dev/null; then
        echo -e "\033[31m[ERROR]\033[0m Certbot is not installed. Please install Certbot to proceed."
        return 1
    fi

    echo -e "\033[33mStopping Apache...\033[0m"
    sudo systemctl stop apache2 || {
        echo -e "\033[31m[ERROR]\033[0m Failed to stop Apache. Exiting..."
        return 1
    }

    if ! wait_for_certbot; then
        echo -e "\033[31m[ERROR]\033[0m Certbot is busy. Please try again later."
        sudo systemctl start apache2 >/dev/null 2>&1
        return 1
    fi

    if sudo certbot renew; then
        echo -e "\033[32mSSL certificates successfully renewed.\033[0m"
    else
        echo -e "\033[31m[ERROR]\033[0m SSL renewal failed. Please check Certbot logs for more details."
        sudo systemctl start apache2
        return 1
    fi

    echo -e "\033[33mRestarting Apache...\033[0m"
    sudo systemctl restart apache2 || {
        echo -e "\033[31m[WARNING]\033[0m Failed to restart Apache. Please check manually."
    }
}
function manage_additional_bots() {
    if [ ! -d "/var/www/html/mirzabotconfig" ]; then
        echo -e "\033[31m[ERROR]\033[0m The main Mirza Bot is not installed (/var/www/html/mirzabotconfig not found)."
        echo -e "\033[33mYou are not allowed to use this section without the main bot installed. Exiting...\033[0m"
        sleep 2
        exit 1
    fi

    if check_marzban_installed; then
        echo -e "\033[31m[ERROR]\033[0m Additional bot management is not available when Marzban is installed."
        echo -e "\033[33mExiting script...\033[0m"
        sleep 2
        exit 1
    fi

    echo -e "\033[36m1) Install Additional Bot\033[0m"
    echo -e "\033[36m2) Update Additional Bot\033[0m"
    echo -e "\033[36m3) Remove Additional Bot\033[0m"
    echo -e "\033[36m4) Export Additional Bot Database\033[0m"
    echo -e "\033[36m5) Import Additional Bot Database\033[0m"
    echo -e "\033[36m6) Configure Automated Backup for Additional Bot\033[0m"
    echo -e "\033[36m7) Disable Automated Backup for Additional Bot\033[0m"
    echo -e "\033[36m8) Change Additional Bot Domain\033[0m"
    echo -e "\033[36m9) Back to Main Menu\033[0m"
    echo ""
    read -p "Select an option [1-9]: " sub_option
    case $sub_option in
        1) install_additional_bot ;;
        2) update_additional_bot ;;
        3) remove_additional_bot ;;
        4) export_additional_bot_database ;;
        5) import_additional_bot_database ;;
        6) configure_backup_additional_bot ;;
        7) disable_backup_additional_bot ;;
        8) change_additional_bot_domain ;;
        9) show_menu ;;
        *)
            echo -e "\033[31mInvalid option. Please try again.\033[0m"
            manage_additional_bots
            ;;
    esac
}
function change_domain() {
    local new_domain current_domainhosts sanitized_value path_segment full_domain_path WEBHOOK_URL webhook_response http_status updated_domainhosts
    full_domain_path=""
    WEBHOOK_URL=""
    while [[ ! "$new_domain" =~ ^[a-zA-Z0-9.-]+$ ]]; do
        read -p "Enter new domain: " new_domain
        [[ ! "$new_domain" =~ ^[a-zA-Z0-9.-]+$ ]] && echo -e "\033[31mInvalid domain format\033[0m"
    done

    log_action "Disabling Apache service before domain change..."
    sudo systemctl disable apache2 >/dev/null 2>&1 || true

    if ! configure_apache_vhost "$new_domain"; then
        log_error "Unable to prepare Apache virtual host for ${new_domain}."
        restore_apache_service
        return 1
    fi

    log_action "Stopping Apache to configure SSL..."
    if ! sudo systemctl stop apache2; then
        log_error "Failed to stop Apache while preparing SSL for ${new_domain}."
        restore_apache_service
        return 1
    fi

    log_action "Configuring SSL certificate for ${new_domain}..."
    if ! wait_for_certbot; then
        log_error "Certbot is already running. Please try again after the current process completes."
        restore_apache_service
        return 1
    fi

    if ! sudo certbot --apache --redirect --agree-tos --preferred-challenges http \
        --non-interactive --force-renewal --cert-name "$new_domain" -d "$new_domain"; then
        log_error "SSL configuration failed for ${new_domain}, rolling back certificate changes."
        if wait_for_certbot; then
            sudo certbot delete --cert-name "$new_domain" 2>/dev/null
        else
            log_warn "Unable to clean up certificate for ${new_domain} because Certbot remained busy."
        fi
        restore_apache_service
        return 1
    fi

    CONFIG_FILE="/var/www/html/mirzabotconfig/config.php"
    if [ -f "$CONFIG_FILE" ]; then
        sudo cp "$CONFIG_FILE" "$CONFIG_FILE.$(date +%s).bak"

        current_domainhosts=$(awk -F"'" '/\$domainhosts/{print $2}' "$CONFIG_FILE" | head -1)
        sanitized_value=${current_domainhosts#http://}
        sanitized_value=${sanitized_value#https://}
        sanitized_value=${sanitized_value#/}
        path_segment=""
        if [[ "$sanitized_value" == */* ]]; then
            path_segment=${sanitized_value#*/}
            path_segment=${path_segment%/}
        fi

        if [ -z "$path_segment" ] && [ -d "/var/www/html/mirzabotconfig" ]; then
            path_segment="mirzabotconfig"
            log_info "No path segment detected in existing domain. Using default path '/mirzabotconfig'."
        fi

        if [ -n "$path_segment" ]; then
            full_domain_path="${new_domain}/${path_segment}"
        else
            full_domain_path="${new_domain}"
        fi

        sudo sed -i "s|\$domainhosts = '.*';|\$domainhosts = '${full_domain_path}';|" "$CONFIG_FILE"

        NEW_SECRET=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9')
        sudo sed -i "s|\$secrettoken = '.*';|\$secrettoken = '${NEW_SECRET}';|" "$CONFIG_FILE"

        BOT_TOKEN=$(awk -F"'" '/\$APIKEY/{print $2}' "$CONFIG_FILE")
        updated_domainhosts=$(awk -F"'" '/\$domainhosts/{print $2}' "$CONFIG_FILE" | head -1)
        updated_domainhosts=${updated_domainhosts%/}
        if [[ "$updated_domainhosts" =~ ^https?:// ]]; then
            WEBHOOK_URL="${updated_domainhosts}/index.php"
        else
            WEBHOOK_URL="https://${updated_domainhosts}/index.php"
        fi

        webhook_response=$(curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
            -F "url=${WEBHOOK_URL}" \
            -F "secret_token=${NEW_SECRET}")

        if echo "$webhook_response" | grep -q '"ok":true'; then
            log_info "Telegram webhook updated successfully for ${new_domain}."
        else
            log_warn "Webhook update returned a warning: ${webhook_response}"
        fi
    else
        log_error "Config file missing at ${CONFIG_FILE}; aborting domain change."
        restore_apache_service
        return 1
    fi

    local attempt http_status=""
    for attempt in {1..5}; do
        http_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$WEBHOOK_URL")
        if [[ "$http_status" =~ ^(200|301|302)$ ]]; then
            break
        fi
        log_warn "Endpoint ${WEBHOOK_URL} not ready yet (HTTP ${http_status:-000}). Retrying in 3 seconds..."
        sleep 3
    done

    if [[ "$http_status" =~ ^(200|301|302)$ ]]; then
        log_info "Domain successfully migrated to ${full_domain_path}. Old configuration cleaned up."
    else
        log_warn "Final verification failed for ${WEBHOOK_URL} (HTTP ${http_status:-000}). Please verify DNS, Apache vhost, and firewall."
    fi

    restore_apache_service
}
function remove_domain() {
    local conf_dir="/etc/apache2/sites-available"
    local domain_list=()
    local domain selection
    local -a conf_files=()

    if [ ! -d "$conf_dir" ]; then
        echo -e "\033[31m[ERROR]\033[0m Apache configuration directory not found."
        read -p "Press Enter to return to main menu..."
        show_menu
        return 1
    fi

    mapfile -t conf_files < <(find "$conf_dir" -maxdepth 1 -type f -name '*.conf' -printf '%f\n' 2>/dev/null | sort)
    for conf in "${conf_files[@]}"; do
        [ -z "$conf" ] && continue
        domain="${conf%.conf}"
        case "$domain" in
            000-default|default-ssl|000-default-le-ssl)
                continue
                ;;
        esac
        domain_list+=("$domain")
    done

    if [ ${#domain_list[@]} -eq 0 ]; then
        echo -e "\033[33m[INFO]\033[0m No custom domains found to remove."
        read -p "Press Enter to return to main menu..."
        show_menu
        return 0
    fi

    echo -e "\033[36mConfigured domains:\033[0m"
    for idx in "${!domain_list[@]}"; do
        printf "%d) %s\n" $((idx + 1)) "${domain_list[$idx]}"
    done

    read -p "Select the domain you want to remove [1-${#domain_list[@]}]: " selection
    if ! [[ "$selection" =~ ^[0-9]+$ ]] || [ "$selection" -lt 1 ] || [ "$selection" -gt ${#domain_list[@]} ]; then
        echo -e "\033[31m[ERROR]\033[0m Invalid selection."
        sleep 2
        show_menu
        return 1
    fi

    domain="${domain_list[$((selection - 1))]}"
    read -p "Are you sure you want to remove ${domain}? (y/n): " confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        echo -e "\033[33m[INFO]\033[0m Operation cancelled."
        sleep 1
        show_menu
        return 0
    fi

    mapfile -t conf_files < <(find "$conf_dir" -maxdepth 1 -type f -name "${domain}*.conf" -printf '%f\n' 2>/dev/null)
    if [ ${#conf_files[@]} -eq 0 ]; then
        conf_files=("${domain}.conf")
    fi

    for conf in "${conf_files[@]}"; do
        [ -z "$conf" ] && continue
        sudo a2dissite "$conf" >/dev/null 2>&1
        sudo rm -f "${conf_dir}/${conf}" "/etc/apache2/sites-enabled/${conf}"
    done

    if ! sudo apache2ctl configtest >/dev/null 2>&1; then
        echo -e "\033[31m[ERROR]\033[0m Apache configuration test failed after removing ${domain}. Please inspect manually."
        show_menu
        return 1
    fi

    if ! sudo systemctl reload apache2 >/dev/null 2>&1; then
        echo -e "\033[33m[WARN]\033[0m Reload failed. Attempting restart..."
        sudo systemctl restart apache2 >/dev/null 2>&1 || echo -e "\033[31m[ERROR]\033[0m Apache restart failed."
    fi

    if [ -d "/etc/letsencrypt/live/${domain}" ]; then
        read -p "Delete existing SSL certificate for ${domain}? (y/n): " delete_cert
        if [[ "$delete_cert" =~ ^[Yy]$ ]]; then
            if wait_for_certbot; then
                sudo certbot delete --cert-name "$domain" 2>/dev/null || echo -e "\033[33m[WARN]\033[0m Failed to delete certificate for ${domain}."
            else
                echo -e "\033[33m[WARN]\033[0m Certbot is busy. Skipping certificate deletion for ${domain}."
            fi
        fi
    fi

    echo -e "\033[32m[SUCCESS]\033[0m Domain ${domain} removed."
    read -p "Press Enter to return to main menu..."
    show_menu
}

delete_cron_jobs() {
    local CRON_FILE="/var/spool/cron/crontabs/www-data"
    while true; do
        local delete_all selection tmp
        clear
        echo -e "\033[33mReading cron jobs for www-data...\033[0m"

        if [ ! -f "$CRON_FILE" ]; then
            echo -e "\033[31m[ERROR]\033[0m Cron file not found at $CRON_FILE."
            read -p "Press Enter to return to main menu..."
            show_menu
            return 1
        fi

        if ! sudo cat "$CRON_FILE" >/dev/null 2>&1; then
            echo -e "\033[31m[ERROR]\033[0m Cannot read $CRON_FILE (permission denied)."
            read -p "Press Enter to return to main menu..."
            show_menu
            return 1
        fi

        mapfile -t CRON_LINES < <(sudo awk '
            /^[[:space:]]*#/ {next}
            /^[[:space:]]*$/ {next}
            {print}
        ' "$CRON_FILE")

        if [ ${#CRON_LINES[@]} -eq 0 ]; then
            echo -e "\033[33m[INFO]\033[0m No cron entries found for www-data."
            read -p "Press Enter to return to main menu..."
            show_menu
            return 0
        fi

        echo -e "\033[36mExisting cron entries:\033[0m"
        for idx in "${!CRON_LINES[@]}"; do
            printf "%d) %s\n" $((idx + 1)) "${CRON_LINES[$idx]}"
        done

        read -p "Delete all detected cron jobs? (y/n): " delete_all
        if [[ "$delete_all" =~ ^[Yy]$ ]]; then
            tmp=$(mktemp)
            if ! sudo awk '
                /^[[:space:]]*#/ {print; next}
                /^[[:space:]]*$/ {print; next}
            ' "$CRON_FILE" > "$tmp"; then
                echo -e "\033[31m[ERROR]\033[0m Failed to clean cron file."
                rm -f "$tmp"
                read -p "Press Enter to return to main menu..."
                show_menu
                return 1
            fi
            if ! sudo mv "$tmp" "$CRON_FILE"; then
                echo -e "\033[31m[ERROR]\033[0m Failed to overwrite cron file."
                rm -f "$tmp"
                read -p "Press Enter to return to main menu..."
                show_menu
                return 1
            fi
            sudo chown www-data:crontab "$CRON_FILE" 2>/dev/null || true
            sudo chmod 600 "$CRON_FILE" 2>/dev/null || true
            echo -e "\033[32m[SUCCESS]\033[0m All detected cron jobs were deleted."
            sleep 1.5
            show_menu
            return 0
        fi

        echo -e "\033[1;31m0) Exit to Main Menu\033[0m"
        echo ""

        read -p "Select a cron entry to delete [0-${#CRON_LINES[@]}]: " selection
        if [[ "$selection" == "0" ]]; then
            echo -e "\033[33mReturning to main menu...\033[0m"
            sleep 1
            show_menu
            return 0
        fi

        if ! [[ "$selection" =~ ^[0-9]+$ ]] || [ "$selection" -lt 1 ] || [ "$selection" -gt ${#CRON_LINES[@]} ]; then
            echo -e "\033[31m[ERROR]\033[0m Invalid selection."
            sleep 1.5
            continue
        fi

        if ! sudo awk -v target="$selection" 'BEGIN{idx=0}
        {
            line=$0
            if (line ~ /^[[:space:]]*$/) {print; next}
            if (line ~ /^[[:space:]]*#/) {print; next}
            idx++
            if (idx==target) next
            print
        }' "$CRON_FILE" > "$tmp"; then
            echo -e "\033[31m[ERROR]\033[0m Failed to update cron file."
            rm -f "$tmp"
            read -p "Press Enter to return to main menu..."
            show_menu
            return 1
        fi

        if ! sudo mv "$tmp" "$CRON_FILE"; then
            echo -e "\033[31m[ERROR]\033[0m Failed to overwrite cron file."
            rm -f "$tmp"
            read -p "Press Enter to return to main menu..."
            show_menu
            return 1
        fi

        sudo chown www-data:crontab "$CRON_FILE" 2>/dev/null || true
        sudo chmod 600 "$CRON_FILE" 2>/dev/null || true

        echo -e "\033[32m[SUCCESS]\033[0m Cron entry #$selection deleted."
        sleep 1.5
    done
}
function install_additional_bot() {
    clear
    echo -e "\033[33mStarting Additional Bot Installation...\033[0m"

    ROOT_CREDENTIALS_FILE="/root/confmirza/dbrootmirza.txt"
    if [[ ! -f "$ROOT_CREDENTIALS_FILE" ]]; then
        echo -e "\033[31mError: Root credentials file not found at $ROOT_CREDENTIALS_FILE.\033[0m"
        echo -ne "\033[36mPlease enter the root MySQL password: \033[0m"
        read -s ROOT_PASS
        echo
        ROOT_USER="root"
    else
        ROOT_USER=$(grep '\$user =' "$ROOT_CREDENTIALS_FILE" | awk -F"'" '{print $2}')
        ROOT_PASS=$(grep '\$pass =' "$ROOT_CREDENTIALS_FILE" | awk -F"'" '{print $2}')
        if [[ -z "$ROOT_USER" || -z "$ROOT_PASS" ]]; then
            echo -e "\033[31mError: Could not extract root credentials from file.\033[0m"
            return 1
        fi
    fi

    while true; do
        echo -ne "\033[36mEnter the domain for the additional bot: \033[0m"
        read DOMAIN_NAME
        if [[ "$DOMAIN_NAME" =~ ^[a-zA-Z0-9.-]+$ ]]; then
            break
        else
            echo -e "\033[31mInvalid domain format. Please try again.\033[0m"
        fi
    done

    echo -e "\033[33mStopping Apache to free port 80...\033[0m"
    sudo systemctl stop apache2

    echo -e "\033[33mObtaining SSL certificate...\033[0m"
    if ! wait_for_certbot; then
        echo -e "\033[31mError: Certbot is busy. Please try again shortly.\033[0m"
        return 1
    fi
    sudo certbot certonly --standalone --agree-tos --preferred-challenges http -d "$DOMAIN_NAME" || {
        echo -e "\033[31mError obtaining SSL certificate.\033[0m"
        return 1
    }

    echo -e "\033[33mRestarting Apache...\033[0m"
    sudo systemctl start apache2

    APACHE_CONFIG="/etc/apache2/sites-available/$DOMAIN_NAME.conf"
    if [[ -f "$APACHE_CONFIG" ]]; then
        echo -e "\033[31mApache configuration for this domain already exists.\033[0m"
        return 1
    fi

    echo -e "\033[33mConfiguring Apache for domain...\033[0m"
    sudo bash -c "cat > $APACHE_CONFIG <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN_NAME
    Redirect permanent / https://$DOMAIN_NAME/
</VirtualHost>

<VirtualHost *:443>
    ServerName $DOMAIN_NAME
    DocumentRoot /var/www/html/$BOT_NAME

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN_NAME/privkey.pem
</VirtualHost>
EOF"

    sudo mkdir -p "/var/www/html/$BOT_NAME"
    sudo a2ensite "$DOMAIN_NAME.conf"
    sudo systemctl reload apache2

    while true; do
        echo -ne "\033[36mEnter the bot name: \033[0m"
        read BOT_NAME
        if [[ "$BOT_NAME" =~ ^[a-zA-Z0-9_-]+$ && ! -d "/var/www/html/$BOT_NAME" ]]; then
            break
        else
            echo -e "\033[31mInvalid or duplicate bot name. Please try again.\033[0m"
        fi
    done

    BOT_DIR="/var/www/html/$BOT_NAME"
    echo -e "\033[33mCloning bot's source code...\033[0m"
    git clone https://github.com/Mmd-Amir/mirza_pro.git "$BOT_DIR" || {
        echo -e "\033[31mError: Failed to clone the repository.\033[0m"
        return 1
    }

    while true; do
        echo -ne "\033[36mEnter the bot token: \033[0m"
        read BOT_TOKEN
        if [[ "$BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; then
            break
        else
            echo -e "\033[31mInvalid bot token format. Please try again.\033[0m"
        fi
    done

    while true; do
        echo -ne "\033[36mEnter the chat ID: \033[0m"
        read CHAT_ID
        if [[ "$CHAT_ID" =~ ^-?[0-9]+$ ]]; then
            break
        else
            echo -e "\033[31mInvalid chat ID format. Please try again.\033[0m"
        fi
    done

    DB_NAME="mirzabot_$BOT_NAME"
    DB_USERNAME="$DB_NAME"
    DEFAULT_PASSWORD=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    echo -ne "\033[36mEnter the database password (default: $DEFAULT_PASSWORD): \033[0m"
    read DB_PASSWORD
    DB_PASSWORD=${DB_PASSWORD:-$DEFAULT_PASSWORD}

    echo -e "\033[33mCreating database and user...\033[0m"
    sudo mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "CREATE DATABASE $DB_NAME;" || {
        echo -e "\033[31mError: Failed to create database.\033[0m"
        return 1
    }
    sudo mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "CREATE USER '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';" || {
        echo -e "\033[31mError: Failed to create database user.\033[0m"
        return 1
    }
    sudo mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USERNAME'@'localhost';" || {
        echo -e "\033[31mError: Failed to grant privileges to user.\033[0m"
        return 1
    }
    sudo mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "FLUSH PRIVILEGES;"

    CONFIG_FILE="$BOT_DIR/config.php"
    echo -e "\033[33mSaving bot configuration...\033[0m"
    cat <<EOF > "$CONFIG_FILE"
<?php
\$APIKEY = '$BOT_TOKEN';
\$usernamedb = '$DB_USERNAME';
\$passworddb = '$DB_PASSWORD';
\$dbname = '$DB_NAME';
\$domainhosts = '$DOMAIN_NAME/$BOT_NAME';
\$adminnumber = '$CHAT_ID';
\$usernamebot = '$BOT_NAME';
\$connect = mysqli_connect('localhost', \$usernamedb, \$passworddb, \$dbname);
if (\$connect->connect_error) {
    die('Database connection failed: ' . \$connect->connect_error);
}
mysqli_set_charset(\$connect, 'utf8mb4');
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
\$dsn = "mysql:host=localhost;dbname=\$dbname;charset=utf8mb4";
try {
     \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options);
} catch (\PDOException \$e) {
     throw new \PDOException(\$e->getMessage(), (int)\$e->getCode());
}
?>
EOF

    sleep 1

    sudo chown -R www-data:www-data "$BOT_DIR"
    sudo chmod -R 755 "$BOT_DIR"

    echo -e "\033[33mSetting webhook for bot...\033[0m"
    curl -F "url=https://$DOMAIN_NAME/$BOT_NAME/index.php" "https://api.telegram.org/bot$BOT_TOKEN/setWebhook" || {
        echo -e "\033[31mError: Failed to set webhook for bot.\033[0m"
        return 1
    }

    MESSAGE="✅ The bot is installed! for start bot send comment /start"
    curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="$MESSAGE" || {
        echo -e "\033[31mError: Failed to send message to Telegram.\033[0m"
        return 1
    }

    TABLE_SETUP_URL="https://${DOMAIN_NAME}/$BOT_NAME/table.php"
    echo -e "\033[33mSetting up database tables...\033[0m"
    curl $TABLE_SETUP_URL || {
        echo -e "\033[31mError: Failed to execute table creation script at $TABLE_SETUP_URL.\033[0m"
        return 1
    }

    echo -e "\033[32mBot installed successfully!\033[0m"
    echo -e "\033[102mDomain Bot: https://$DOMAIN_NAME\033[0m"
    echo -e "\033[104mDatabase address: https://$DOMAIN_NAME/phpmyadmin\033[0m"
    echo -e "\033[33mDatabase name: \033[36m$DB_NAME\033[0m"
    echo -e "\033[33mDatabase username: \033[36m$DB_USERNAME\033[0m"
    echo -e "\033[33mDatabase password: \033[36m$DB_PASSWORD\033[0m"
}


function update_additional_bot() {
    clear
    echo -e "\033[36mAvailable Bots:\033[0m"

    BOT_DIRS=$(ls -d /var/www/html/*/ 2>/dev/null | grep -v "/var/www/html/mirzabotconfig/" | xargs -r -n 1 basename)

    if [ -z "$BOT_DIRS" ]; then
        echo -e "\033[31mNo additional bots found in /var/www/html.\033[0m"
        return 1
    fi

    echo "$BOT_DIRS" | nl -w 2 -s ") "

    echo -ne "\033[36mSelect a bot by name: \033[0m"
    read SELECTED_BOT

    if [[ ! "$BOT_DIRS" =~ (^|[[:space:]])$SELECTED_BOT($|[[:space:]]) ]]; then
        echo -e "\033[31mInvalid bot name.\033[0m"
        return 1
    fi

    BOT_PATH="/var/www/html/$SELECTED_BOT"
    BOT_PARENT_DIR="$(dirname "$BOT_PATH")"
    CONFIG_PATH="$BOT_PATH/config.php"
    TEMP_CONFIG_PATH="/root/${SELECTED_BOT}_config.php"

    echo -e "\033[33mUpdating $SELECTED_BOT...\033[0m"

    if [ -f "$CONFIG_PATH" ]; then
        mv "$CONFIG_PATH" "$TEMP_CONFIG_PATH" || {
            echo -e "\033[31mFailed to backup config.php. Exiting...\033[0m"
            return 1
        }
    else
        echo -e "\033[31mconfig.php not found in $BOT_PATH. Exiting...\033[0m"
        return 1
    fi

    if ! rm -rf "$BOT_PATH"; then
        echo -e "\033[31mFailed to remove old bot directory. Exiting...\033[0m"
        return 1
    fi

    if ! git clone https://github.com/Mmd-Amir/mirza_pro.git "$BOT_PATH"; then
        echo -e "\033[31mFailed to clone the repository. Exiting...\033[0m"
        return 1
    fi

    if ! mv "$TEMP_CONFIG_PATH" "$CONFIG_PATH"; then
        echo -e "\033[31mFailed to restore config.php. Exiting...\033[0m"
        return 1
    fi

    sudo chown -R www-data:www-data "$BOT_PATH"
    sudo chmod -R 755 "$BOT_PATH"

    URL=$(grep '\$domainhosts' "$CONFIG_PATH" | cut -d"'" -f2)
    if [ -z "$URL" ]; then
        echo -e "\033[31mFailed to extract domain URL from config.php. Exiting...\033[0m"
        return 1
    fi

    if ! curl -s "https://$URL/mirzabotconfig/table.php"; then
        echo -e "\033[31mFailed to execute table.php. Exiting...\033[0m"
        return 1
    fi

    echo -e "\033[32m$SELECTED_BOT has been successfully updated!\033[0m"
}

function remove_additional_bot() {
    clear
    echo -e "\033[36mAvailable Bots:\033[0m"

    BOT_DIRS=$(ls -d /var/www/html/*/ 2>/dev/null | grep -v "/var/www/html/mirzabotconfig/" | xargs -r -n 1 basename)

    if [ -z "$BOT_DIRS" ]; then
        echo -e "\033[31mNo additional bots found in /var/www/html.\033[0m"
        return 1
    fi

    echo "$BOT_DIRS" | nl -w 2 -s ") "

    echo -ne "\033[36mSelect a bot by name: \033[0m"
    read SELECTED_BOT

    if [[ ! "$BOT_DIRS" =~ (^|[[:space:]])$SELECTED_BOT($|[[:space:]]) ]]; then
        echo -e "\033[31mInvalid bot name.\033[0m"
        return 1
    fi

    BOT_PATH="/var/www/html/$SELECTED_BOT"
    CONFIG_PATH="$BOT_PATH/config.php"

    echo -ne "\033[36mAre you sure you want to remove $SELECTED_BOT? (yes/no): \033[0m"
    read CONFIRM_REMOVE
    if [[ "$CONFIRM_REMOVE" != "yes" ]]; then
        echo -e "\033[33mAborted.\033[0m"
        return 1
    fi

    echo -ne "\033[36mHave you backed up the database? (yes/no): \033[0m"
    read BACKUP_CONFIRM
    if [[ "$BACKUP_CONFIRM" != "yes" ]]; then
        echo -e "\033[33mAborted. Please backup the database first.\033[0m"
        return 1
    fi

    ROOT_CREDENTIALS_FILE="/root/confmirza/dbrootmirza.txt"
    if [ -f "$ROOT_CREDENTIALS_FILE" ]; then
        ROOT_USER=$(grep '\$user =' "$ROOT_CREDENTIALS_FILE" | awk -F"'" '{print $2}')
        ROOT_PASS=$(grep '\$pass =' "$ROOT_CREDENTIALS_FILE" | awk -F"'" '{print $2}')
    else
        echo -ne "\033[36mRoot credentials file not found. Enter MySQL root password: \033[0m"
        read -s ROOT_PASS
        echo
        ROOT_USER="root"
    fi

    DOMAIN_NAME=$(grep '\$domainhosts' "$CONFIG_PATH" | cut -d"'" -f2 | cut -d"/" -f1)
    DB_NAME=$(awk -F"'" '/\$dbname = / {print $2}' "$CONFIG_PATH")
    DB_USER=$(awk -F"'" '/\$usernamedb = / {print $2}' "$CONFIG_PATH")

    echo "ROOT_USER: $ROOT_USER" > /tmp/remove_bot_debug.log
    echo "ROOT_PASS: $ROOT_PASS" >> /tmp/remove_bot_debug.log
    echo "DB_NAME: $DB_NAME" >> /tmp/remove_bot_debug.log
    echo "DB_USER: $DB_USER" >> /tmp/remove_bot_debug.log

    echo -e "\033[33mRemoving database $DB_NAME...\033[0m"
    mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" 2>/tmp/db_remove_error.log
    if [ $? -eq 0 ]; then
        echo -e "\033[32mDatabase $DB_NAME removed successfully.\033[0m"
    else
        echo -e "\033[31mFailed to remove database $DB_NAME.\033[0m"
        cat /tmp/db_remove_error.log >> /tmp/remove_bot_debug.log
    fi

    echo -e "\033[33mRemoving user $DB_USER...\033[0m"
    mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "DROP USER IF EXISTS '$DB_USER'@'localhost';" 2>/tmp/user_remove_error.log
    if [ $? -eq 0 ]; then
        echo -e "\033[32mUser $DB_USER removed successfully.\033[0m"
    else
        echo -e "\033[31mFailed to remove user $DB_USER.\033[0m"
        cat /tmp/user_remove_error.log >> /tmp/remove_bot_debug.log
    fi

    echo -e "\033[33mRemoving bot directory $BOT_PATH...\033[0m"
    if ! rm -rf "$BOT_PATH"; then
        echo -e "\033[31mFailed to remove bot directory.\033[0m"
        return 1
    fi

    APACHE_CONF="/etc/apache2/sites-available/$DOMAIN_NAME.conf"
    if [ -f "$APACHE_CONF" ]; then
        echo -e "\033[33mRemoving Apache configuration for $DOMAIN_NAME...\033[0m"
        sudo a2dissite "$DOMAIN_NAME.conf"
        rm -f "$APACHE_CONF"
        rm -f "/etc/apache2/sites-enabled/$DOMAIN_NAME.conf"
        sudo systemctl reload apache2
    else
        echo -e "\033[31mApache configuration for $DOMAIN_NAME not found.\033[0m"
    fi

    echo -e "\033[32m$SELECTED_BOT has been successfully removed.\033[0m"
}

function export_additional_bot_database() {
    clear
    echo -e "\033[36mAvailable Bots:\033[0m"

    BOT_DIRS=$(ls -d /var/www/html/*/ 2>/dev/null | grep -v "/var/www/html/mirzabotconfig/" | xargs -r -n 1 basename)

    if [ -z "$BOT_DIRS" ]; then
        echo -e "\033[31mNo additional bots found in /var/www/html.\033[0m"
        return 1
    fi

    echo "$BOT_DIRS" | nl -w 2 -s ") "

    echo -ne "\033[36mEnter the bot name: \033[0m"
    read SELECTED_BOT

    if [[ ! "$BOT_DIRS" =~ (^|[[:space:]])$SELECTED_BOT($|[[:space:]]) ]]; then
        echo -e "\033[31mInvalid bot name.\033[0m"
        return 1
    fi

    BOT_PATH="/var/www/html/$SELECTED_BOT"  
    CONFIG_PATH="$BOT_PATH/config.php"      

    if [ ! -f "$CONFIG_PATH" ]; then
        echo -e "\033[31mconfig.php not found for $SELECTED_BOT.\033[0m"
        return 1
    fi

    ROOT_CREDENTIALS_FILE="/root/confmirza/dbrootmirza.txt"
    if [ -f "$ROOT_CREDENTIALS_FILE" ]; then
        ROOT_USER=$(grep '\$user =' "$ROOT_CREDENTIALS_FILE" | awk -F"'" '{print $2}')
        ROOT_PASS=$(grep '\$pass =' "$ROOT_CREDENTIALS_FILE" | awk -F"'" '{print $2}')
    else
        echo -e "\033[31mRoot credentials file not found.\033[0m"
        echo -ne "\033[36mEnter MySQL root password: \033[0m"
        read -s ROOT_PASS
        echo

        if [ -z "$ROOT_PASS" ]; then
            echo -e "\033[31mPassword cannot be empty. Exiting...\033[0m"
            return 1
        fi

        ROOT_USER="root"

        echo "SELECT 1" | mysql -u "$ROOT_USER" -p"$ROOT_PASS" 2>/dev/null
        if [ $? -ne 0 ]; then
            echo -e "\033[31mInvalid root credentials. Exiting...\033[0m"
            return 1
        fi
    fi

    DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASS=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_NAME=$(grep '^\$dbname' "$CONFIG_PATH" | awk -F"'" '{print $2}')

    if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
        echo -e "\033[31m[ERROR]\033[0m Failed to extract database credentials from $CONFIG_PATH."
        return 1
    fi

    echo -e "\033[33mVerifying database existence...\033[0m"
    if ! mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
        echo -e "\033[31m[ERROR]\033[0m Database $DB_NAME does not exist or credentials are incorrect."
        return 1
    fi

    BACKUP_FILE="/root/${DB_NAME}_backup.sql"
    echo -e "\033[33mCreating backup at $BACKUP_FILE...\033[0m"
    if ! mysqldump -u "$ROOT_USER" -p"$ROOT_PASS" "$DB_NAME" > "$BACKUP_FILE"; then
        echo -e "\033[31m[ERROR]\033[0m Failed to create database backup."
        return 1
    fi

    echo -e "\033[32mBackup successfully created at $BACKUP_FILE.\033[0m"
}

function import_additional_bot_database() {
    clear
    echo -e "\033[36mStarting Import Database Process...\033[0m"

    ROOT_CREDENTIALS_FILE="/root/confmirza/dbrootmirza.txt"
    if [ -f "$ROOT_CREDENTIALS_FILE" ]; then
        ROOT_USER=$(grep '\$user =' "$ROOT_CREDENTIALS_FILE" | awk -F"'" '{print $2}')
        ROOT_PASS=$(grep '\$pass =' "$ROOT_CREDENTIALS_FILE" | awk -F"'" '{print $2}')
    else
        echo -e "\033[31mRoot credentials file not found.\033[0m"
        echo -ne "\033[36mEnter MySQL root password: \033[0m"
        read -s ROOT_PASS
        echo

        if [ -z "$ROOT_PASS" ]; then
            echo -e "\033[31mPassword cannot be empty. Exiting...\033[0m"
            return 1
        fi

        ROOT_USER="root"

        echo "SELECT 1" | mysql -u "$ROOT_USER" -p"$ROOT_PASS" 2>/dev/null
        if [ $? -ne 0 ]; then
            echo -e "\033[31mInvalid root credentials. Exiting...\033[0m"
            return 1
        fi
    fi

    SQL_FILES=$(find /root -maxdepth 1 -type f -name "*.sql")
    if [ -z "$SQL_FILES" ]; then
        echo -e "\033[31mNo .sql files found in /root. Please provide a valid .sql file.\033[0m"
        return 1
    fi

    echo -e "\033[36mAvailable .sql files:\033[0m"
    echo "$SQL_FILES" | nl -w 2 -s ") "

    echo -ne "\033[36mEnter the number of the file or provide a full path: \033[0m"
    read FILE_SELECTION

    if [[ "$FILE_SELECTION" =~ ^[0-9]+$ ]]; then
        SELECTED_FILE=$(echo "$SQL_FILES" | sed -n "${FILE_SELECTION}p")
    else
        SELECTED_FILE="$FILE_SELECTION"
    fi

    if [ ! -f "$SELECTED_FILE" ]; then
        echo -e "\033[31mSelected file does not exist. Exiting...\033[0m"
        return 1
    fi

    echo -e "\033[36mAvailable Bots:\033[0m"
    BOT_DIRS=$(ls -d /var/www/html/*/ 2>/dev/null | grep -v "/var/www/html/mirzabotconfig/" | xargs -r -n 1 basename)

    if [ -z "$BOT_DIRS" ]; then
        echo -e "\033[31mNo additional bots found in /var/www/html.\033[0m"
        return 1
    fi

    echo "$BOT_DIRS" | nl -w 2 -s ") "

    echo -ne "\033[36mSelect a bot by name: \033[0m"
    read SELECTED_BOT

    if [[ ! "$BOT_DIRS" =~ (^|[[:space:]])$SELECTED_BOT($|[[:space:]]) ]]; then
        echo -e "\033[31mInvalid bot name.\033[0m"
        return 1
    fi

    BOT_PATH="/var/www/html/$SELECTED_BOT"  
    CONFIG_PATH="$BOT_PATH/config.php"      

    if [ ! -f "$CONFIG_PATH" ]; then
        echo -e "\033[31mconfig.php not found for $SELECTED_BOT.\033[0m"
        return 1
    fi

    DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASS=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_NAME=$(grep '^\$dbname' "$CONFIG_PATH" | awk -F"'" '{print $2}')

    if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
        echo -e "\033[31m[ERROR]\033[0m Failed to extract database credentials from $CONFIG_PATH."
        return 1
    fi

    echo -e "\033[33mVerifying database existence...\033[0m"
    if ! mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
        echo -e "\033[31m[ERROR]\033[0m Database $DB_NAME does not exist or credentials are incorrect."
        return 1
    fi

    echo -e "\033[33mImporting database from $SELECTED_FILE into $DB_NAME...\033[0m"
    if ! mysql -u "$ROOT_USER" -p"$ROOT_PASS" "$DB_NAME" < "$SELECTED_FILE"; then
        echo -e "\033[31m[ERROR]\033[0m Failed to import database."
        return 1
    fi

    echo -e "\033[32mDatabase successfully imported from $SELECTED_FILE into $DB_NAME.\033[0m"
}

function disable_backup_additional_bot() {
    clear
    echo -e "\033[36mDisabling Automated Backup for Additional Bot...\033[0m"

    echo -e "\033[36mAvailable Bots:\033[0m"
    BOT_DIRS=$(ls -d /var/www/html/*/ 2>/dev/null | grep -v "/var/www/html/mirzabotconfig/" | xargs -r -n 1 basename)

    if [ -z "$BOT_DIRS" ]; then
        echo -e "\033[31mNo additional bots found in /var/www/html.\033[0m"
        return 1
    fi

    echo "$BOT_DIRS" | nl -w 2 -s ") "

    echo -ne "\033[36mSelect a bot by name: \033[0m"
    read SELECTED_BOT

    if [[ ! "$BOT_DIRS" =~ (^|[[:space:]])$SELECTED_BOT($|[[:space:]]) ]]; then
        echo -e "\033[31mInvalid bot name.\033[0m"
        return 1
    fi

    BACKUP_SCRIPT="/root/${SELECTED_BOT}_auto_backup.sh"

    CURRENT_CRON=$(crontab -l 2>/dev/null | grep "$BACKUP_SCRIPT")

    if [ -z "$CURRENT_CRON" ]; then
        echo -e "\033[33mNo automated backup found for $SELECTED_BOT.\033[0m"
        return 1
    fi

    crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" | crontab -

    if [ -f "$BACKUP_SCRIPT" ]; then
        rm "$BACKUP_SCRIPT"
    fi

    echo -e "\033[32mAutomated backup disabled successfully for $SELECTED_BOT.\033[0m"
}

function change_additional_bot_domain() {
    clear
    echo -e "\033[33mChange Additional Bot Domain\033[0m"
    log_action "Initiating additional bot domain change workflow."

    echo -e "\033[36mAvailable Bots:\033[0m"
    BOT_DIRS=$(ls -d /var/www/html/*/ 2>/dev/null | grep -v "/var/www/html/mirzabotconfig/" | xargs -r -n 1 basename)

    if [ -z "$BOT_DIRS" ]; then
        echo -e "\033[31mNo additional bots found in /var/www/html.\033[0m"
        log_warn "No additional bots detected during domain change request."
        return 1
    fi

    echo "$BOT_DIRS" | nl -w 2 -s ") "

    echo -ne "\033[36mSelect a bot by name: \033[0m"
    read SELECTED_BOT

    if [[ ! "$BOT_DIRS" =~ (^|[[:space:]])$SELECTED_BOT($|[[:space:]]) ]]; then
        echo -e "\033[31mInvalid bot name.\033[0m"
        log_error "User selected invalid bot '$SELECTED_BOT' for domain change."
        return 1
    fi

    BOT_PATH="/var/www/html/$SELECTED_BOT"
    CONFIG_PATH="$BOT_PATH/config.php"

    if [ ! -f "$CONFIG_PATH" ]; then
        echo -e "\033[31mconfig.php not found for $SELECTED_BOT.\033[0m"
        log_error "config.php missing for $SELECTED_BOT while changing domain."
        return 1
    fi

    current_domainhosts=$(grep '^\$domainhosts' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    sanitized=${current_domainhosts#http://}
    sanitized=${sanitized#https://}
    sanitized=${sanitized#/}
    current_domain=${sanitized%%/*}
    log_info "Processing domain change for bot '$SELECTED_BOT' (current domain: ${current_domain:-unknown})."

    while true; do
        echo -ne "\033[36mEnter the new domain (e.g., example.com): \033[0m"
        read NEW_DOMAIN
        if [[ "$NEW_DOMAIN" =~ ^[a-zA-Z0-9.-]+$ ]]; then
            log_info "User entered new domain '$NEW_DOMAIN' for bot '$SELECTED_BOT'."
            break
        fi
        echo -e "\033[31mInvalid domain format. Please try again.\033[0m"
    done

    log_action "Disabling Apache service before domain change..."
    sudo systemctl disable apache2 >/dev/null 2>&1 || true

    if ! configure_apache_vhost "$NEW_DOMAIN" "$BOT_PARENT_DIR"; then
        log_error "Unable to prepare Apache virtual host for ${NEW_DOMAIN} (bot ${SELECTED_BOT})."
        restore_apache_service
        return 1
    fi

    log_action "Stopping Apache to configure SSL..."
    if ! sudo systemctl stop apache2; then
        log_error "Failed to stop Apache while preparing SSL for ${NEW_DOMAIN}."
        restore_apache_service
        return 1
    fi

    log_action "Configuring SSL certificate for ${NEW_DOMAIN}..."
    if ! wait_for_certbot; then
        log_error "Certbot is already running. Please try again after the current process completes."
        restore_apache_service
        return 1
    fi

    if ! sudo certbot --apache --redirect --agree-tos --preferred-challenges http \
        --non-interactive --force-renewal --cert-name "$NEW_DOMAIN" -d "$NEW_DOMAIN"; then
        log_error "SSL configuration failed for ${NEW_DOMAIN}, rolling back certificate changes."
        if wait_for_certbot; then
            sudo certbot delete --cert-name "$NEW_DOMAIN" 2>/dev/null
        else
            log_warn "Unable to clean up certificate for ${NEW_DOMAIN} because Certbot remained busy."
        fi
        restore_apache_service
        return 1
    fi

    if [ -f "$CONFIG_PATH" ]; then
        sudo cp "$CONFIG_PATH" "$CONFIG_PATH.$(date +%s).bak"

        sanitized=${current_domainhosts#http://}
        sanitized=${sanitized#https://}
        sanitized=${sanitized#/}
        path_segment=""
        if [[ "$sanitized" == */* ]]; then
            path_segment=${sanitized#*/}
            path_segment=${path_segment%/}
        fi
        if [ -z "$path_segment" ]; then
            path_segment="$SELECTED_BOT"
        fi

        full_domain_path="${NEW_DOMAIN}/${path_segment}"

        sudo sed -i "s|\$domainhosts = '.*';|\$domainhosts = '${full_domain_path}';|" "$CONFIG_PATH"

        NEW_SECRET=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9')
        sudo sed -i "s|\$secrettoken = '.*';|\$secrettoken = '${NEW_SECRET}';|" "$CONFIG_PATH"

        BOT_TOKEN=$(awk -F"'" '/\$APIKEY/{print $2}' "$CONFIG_PATH")
        updated_domainhosts=$(awk -F"'" '/\$domainhosts/{print $2}' "$CONFIG_PATH" | head -1)
        updated_domainhosts=${updated_domainhosts%/}
        if [[ "$updated_domainhosts" =~ ^https?:// ]]; then
            WEBHOOK_URL="${updated_domainhosts}/index.php"
        else
            WEBHOOK_URL="https://${updated_domainhosts}/index.php"
        fi

        webhook_response=$(curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
            -F "url=${WEBHOOK_URL}" \
            -F "secret_token=${NEW_SECRET}")

        if echo "$webhook_response" | grep -q '"ok":true'; then
            log_info "Telegram webhook updated successfully for ${NEW_DOMAIN} (bot ${SELECTED_BOT})."
        else
            log_warn "Webhook update returned a warning for ${NEW_DOMAIN}: ${webhook_response}"
        fi
    else
        log_error "Config file missing at ${CONFIG_PATH}; aborting domain change."
        restore_apache_service
        return 1
    fi

    if [ -n "$current_domain" ] && [ "$current_domain" != "$NEW_DOMAIN" ] && [ -f "/etc/apache2/sites-available/${current_domain}.conf" ]; then
        sudo a2dissite "${current_domain}.conf" >/dev/null 2>&1
        log_info "Disabled old Apache site ${current_domain}.conf."
    fi

    local attempt http_status=""
    for attempt in {1..5}; do
        http_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$WEBHOOK_URL")
        if [[ "$http_status" =~ ^(200|301|302)$ ]]; then
            break
        fi
        log_warn "Endpoint ${WEBHOOK_URL} not ready yet (HTTP ${http_status:-000}). Retrying in 3 seconds..."
        sleep 3
    done

    if [[ "$http_status" =~ ^(200|301|302)$ ]]; then
        log_info "Additional bot domain successfully migrated to ${full_domain_path}."
    else
        log_warn "Final verification failed for ${WEBHOOK_URL} (HTTP ${http_status:-000}). Please verify DNS, Apache vhost, and firewall."
    fi

    if [ -n "$current_domain" ] && [ "$current_domain" != "$NEW_DOMAIN" ] && [ -d "/etc/letsencrypt/live/${current_domain}" ]; then
        read -p "Delete old SSL certificate for ${current_domain}? (y/n): " delete_old_cert
        if [[ "$delete_old_cert" =~ ^[Yy]$ ]]; then
            if wait_for_certbot; then
                sudo certbot delete --cert-name "$current_domain" 2>/dev/null || echo -e "\033[33m[WARN]\033[0m Failed to delete certificate for ${current_domain}."
                log_info "Requested deletion of old certificate for ${current_domain}."
            else
                log_warn "Certbot is busy; skipping deletion of legacy certificate for ${current_domain}."
            fi
        fi
    fi

    echo -e "\033[32mDomain updated successfully for ${SELECTED_BOT}.\033[0m"
    log_info "Domain change completed for '${SELECTED_BOT}'. New domain: $NEW_DOMAIN."
    restore_apache_service
    read -p "Press Enter to return to the Additional Bot menu..."
    manage_additional_bots
}

function configure_backup_additional_bot() {
    clear
    echo -e "\033[36mConfiguring Automated Backup for Additional Bot...\033[0m"

    echo -e "\033[36mAvailable Bots:\033[0m"
    BOT_DIRS=$(ls -d /var/www/html/*/ 2>/dev/null | grep -v "/var/www/html/mirzabotconfig/" | xargs -r -n 1 basename)

    if [ -z "$BOT_DIRS" ]; then
        echo -e "\033[31mNo additional bots found in /var/www/html.\033[0m"
        return 1
    fi

    echo "$BOT_DIRS" | nl -w 2 -s ") "

    echo -ne "\033[36mSelect a bot by name: \033[0m"
    read SELECTED_BOT

    if [[ ! "$BOT_DIRS" =~ (^|[[:space:]])$SELECTED_BOT($|[[:space:]]) ]]; then
        echo -e "\033[31mInvalid bot name.\033[0m"
        return 1
    fi

    BOT_PATH="/var/www/html/$SELECTED_BOT"
    CONFIG_PATH="$BOT_PATH/config.php"

    if [ ! -f "$CONFIG_PATH" ]; then
        echo -e "\033[31mconfig.php not found for $SELECTED_BOT.\033[0m"
        return 1
    fi

    DB_NAME=$(grep '^\$dbname' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASS=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    TELEGRAM_TOKEN=$(grep '^\$APIKEY' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    TELEGRAM_CHAT_ID=$(grep '^\$adminnumber' "$CONFIG_PATH" | awk -F"'" '{print $2}')

    if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
        echo -e "\033[31m[ERROR]\033[0m Failed to extract database credentials from $CONFIG_PATH."
        return 1
    fi

    if [ -z "$TELEGRAM_TOKEN" ] || [ -z "$TELEGRAM_CHAT_ID" ]; then
        echo -e "\033[31m[ERROR]\033[0m Telegram token or chat ID not found in $CONFIG_PATH."
        return 1
    fi

    while true; do
        echo -e "\033[36mChoose backup frequency:\033[0m"
        echo -e "\033[36m1) Every minute\033[0m"
        echo -e "\033[36m2) Every hour\033[0m"
        echo -e "\033[36m3) Every day\033[0m"
        echo -e "\033[36m4) Every week\033[0m"
        read -p "Enter your choice (1-4): " frequency

        case $frequency in
            1) cron_time="* * * * *" ; break ;;
            2) cron_time="0 * * * *" ; break ;;
            3) cron_time="0 0 * * *" ; break ;;
            4) cron_time="0 0 * * 0" ; break ;;
            *)
                echo -e "\033[31mInvalid option. Please try again.\033[0m"
                ;;
        esac
    done

    BACKUP_SCRIPT="/root/${SELECTED_BOT}_auto_backup.sh"
    cat <<EOF > "$BACKUP_SCRIPT"

DB_NAME="$DB_NAME"
DB_USER="$DB_USER"
DB_PASS="$DB_PASS"
TELEGRAM_TOKEN="$TELEGRAM_TOKEN"
TELEGRAM_CHAT_ID="$TELEGRAM_CHAT_ID"

BACKUP_FILE="/root/\${DB_NAME}_\$(date +"%Y%m%d_%H%M%S").sql"
if mysqldump -u "\$DB_USER" -p"\$DB_PASS" "\$DB_NAME" > "\$BACKUP_FILE"; then
    curl -F document=@"\$BACKUP_FILE" "https://api.telegram.org/bot\$TELEGRAM_TOKEN/sendDocument" -F chat_id="\$TELEGRAM_CHAT_ID"
    if [ \$? -eq 0 ]; then
        rm "\$BACKUP_FILE"
    fi
else
    echo -e "\033[31m[ERROR]\033[0m Failed to create database backup."
fi
EOF

    chmod +x "$BACKUP_SCRIPT"

    (crontab -l 2>/dev/null; echo "$cron_time bash $BACKUP_SCRIPT") | crontab -

    echo -e "\033[32mAutomated backup configured successfully for $SELECTED_BOT.\033[0m"
}

process_arguments() {
    local version=""
    case "$1" in
        -v*)
            version="${1#-v}"
            if [ -n "$version" ]; then
                install_bot "-v" "$version"
            else
                if [ -n "$2" ]; then
                    install_bot "-v" "$2"
                else
                    echo -e "\033[31m[ERROR]\033[0m Please specify a version with -v (e.g., -v 4.11.1)"
                    exit 1
                fi
            fi
            ;;
        -beta)
            install_bot "-beta"
            ;;
        --beta)
            install_bot "-beta"
            ;;
        -update)
            update_bot "$2"
            ;;
        *)
            show_menu
            ;;
    esac
}

process_arguments "$1" "$2"

