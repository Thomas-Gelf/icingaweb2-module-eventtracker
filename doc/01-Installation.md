
```shell
# You can customize these settings, but we suggest to stick with our defaults:
MODULE_VERSION="0.10.0"
DAEMON_USER="eventtracker"
DAEMON_GROUP="icingaweb2"
ICINGAWEB_MODULEPATH="/usr/share/icingaweb2/modules"
REPO_URL="https://github.com/icinga/icingaweb2-module-eventtracker"
TARGET_DIR="${ICINGAWEB_MODULEPATH}/eventtracker"
URL="${REPO_URL}/archive/refs/tags/v${MODULE_VERSION}.tar.gz"

# systemd defaults:
SOCKET_PATH=/run/icinga-eventtracker
TMPFILES_CONFIG=/etc/tmpfiles.d/icinga-eventtracker.conf

getent passwd "${DAEMON_USER}" > /dev/null || useradd -r -g "${DAEMON_GROUP}" \
  -d /var/lib/${DAEMON_USER} -s /bin/false ${DAEMON_USER}
install -d -o "${DAEMON_USER}" -g "${DAEMON_GROUP}" -m 0750 /var/lib/${DAEMON_USER}
install -d -m 0755 "${TARGET_DIR}"

test -d "${TARGET_DIR}_TMP" && rm -rf "${TARGET_DIR}_TMP"
test -d "${TARGET_DIR}_BACKUP" && rm -rf "${TARGET_DIR}_BACKUP"
install -d -o root -g root -m 0755 "${TARGET_DIR}_TMP"
wget -q -O - "$URL" | tar xfz - -C "${TARGET_DIR}_TMP" --strip-components 1 \
  && mv "${TARGET_DIR}" "${TARGET_DIR}_BACKUP" \
  && mv "${TARGET_DIR}_TMP" "${TARGET_DIR}" \
  && rm -rf "${TARGET_DIR}_BACKUP"

echo "d ${SOCKET_PATH} 0755 ${DAEMON_USER} ${DAEMON_GROUP} -" > "${TMPFILES_CONFIG}"
cp -f "${TARGET_DIR}/contrib/systemd/icinga-eventtracker.service" /etc/systemd/system/
systemd-tmpfiles --create "${TMPFILES_CONFIG}"

icingacli module enable eventtracker
systemctl daemon-reload
systemctl enable icinga-eventtracker.service
systemctl restart icinga-eventtracker.service
```
