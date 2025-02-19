%define revision 1
%define git_version %( git describe --tags | cut -c2- | tr -s '-' '+')
%define git_hash %( git rev-parse --short HEAD )
%define daemon_user eventtracker
%define daemon_group icingaweb2
%define daemon_home /var/lib/%{daemon_user}
%define basedir         %{_datadir}/%{name}
%define bindir          %{_bindir}
%define socket_path /run/icinga-eventtracker
%undefine __brp_mangle_shebangs

Name:           icingaweb2-module-eventtracker
Version:        %{git_version}
Release:        %{revision}%{?dist}
Summary:        Event Tracker for Icinga
Group:          Applications/System
License:        MIT
URL:            https://github.com/Thomas-Gelf
Source0:        https://github.com/Thomas-Gelf/icingaweb2-module-eventtracker/archive/%{git_hash}.tar.gz
BuildArch:      noarch
BuildRoot:      %{_tmppath}/%{name}-%{git_version}-%{release}
Packager:       Thomas Gelf <thomas@gelf.net>
Requires:       icingaweb2

%description
Event Tracker for Icinga

%prep

%build

%install
rm -rf %{buildroot}
mkdir -p %{buildroot}
mkdir -p %{buildroot}%{bindir}
mkdir -p %{buildroot}%{basedir}
mkdir -p %{buildroot}/lib/systemd/system
mkdir -p %{buildroot}/lib/tmpfiles.d
cd - # ???
cp -pr  application doc library public schema vendor configuration.php run.php README.md %{buildroot}%{basedir}/
cp -pr contrib/systemd/icinga-eventtracker.service %{buildroot}/lib/systemd/system/
echo "d %{socket_path} 0755 %{daemon_user} %{daemon_group} -" > %{buildroot}/lib/tmpfiles.d/icinga-eventtracker.conf

%pre
getent passwd "%{daemon_user}" > /dev/null || useradd -r -g "%{daemon_group}" \
-d "%{daemon_home}" -s "/sbin/nologin" "%{daemon_user}"
install -d -o "%{daemon_user}" -g "%{daemon_group}" -m 0750 "%{daemon_home}"

%post
systemd-tmpfiles --create /lib/tmpfiles.d/icinga-eventtracker.conf
systemctl daemon-reload

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,root)
%{basedir}
/lib/systemd/system/icinga-eventtracker.service
/lib/tmpfiles.d/icinga-eventtracker.conf

%changelog
* Wed Feb 19 2025 Thomas Gelf <thomas@gelf.net> 0.0.1
- Initial packaging
