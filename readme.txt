# keymanager
This is a tool to manage and distribute SSH keys using rsync

I created this project to facilitate the move to key based authentication for my customers. In the setup as I use it there is a dedicated VM/container that is fully encrypted and has access through SSH on all servers that it needs to manage. The dedicated machine acts as a GIT repository with a hook script that runs the actual keymanager script to deploy the keys. The GIT repository contains only 4 files. I will use "admin" as the generic admin user but you can change it to whatever you want. {keymanager_public_key} is were you will have to fill in the RSA string of your public key of the user that will push the keys. ex. git@keymanager.yourdomain.com

access

# this is a comma separated access list
# a colon is used to separate the values like this
# alias of users or a user COLON alias of servers or a server COLON user_on_server1
# multiple users servers or server_users and aliases are possible as long a they are
# separated by commas
# To disable access for a certain user to a specific server
# replace the username with the word "disabled" 
# disabled COLON <server> COLON <user_on_server>

aliases

# this file defines aliases aka groups
# you can make groups of users, machines and aliases
#
# 

keys

# this is the key that needs to be added to the admin.auth file of every newly added machine
# never remove this key and never alter this key
# if ever necessary this key has to be altered in the config.php and
# pushed to every machine before being changed in the .ssh/ folder of the git user on keymanager
#
keymanager_git,ssh-rsa,{keymanager_public_key}
# Do not remove the following line as it is required to disable access
disabled,ssh-rsa,XXXXXXXXXXXXXXXXXXX
# users
#

nondefaulthosts

# This file is used to make the keymanager use a special command for a host
# ex. default_2201 is the default rsync command but using port 2201 instead of 22 to connect to the destination host
# commands need to be defined in /home/git/scripts/config.php, please be careful since there is no version control for these files!

On every machine you want to manage make sure you have a generic user (ex. admin) that has full sudo permission that holds the key of the git user. Centralise your authorization files and make sure rsync is installed and that the git machine has access to port your SSH server is listening to.

One liner to prepare a machine for key management (this can be used as a post bash script on spacewalk for instance)

adduser admin; echo 'admin        ALL=(ALL)       NOPASSWD: ALL
Defaults:admin       !requiretty' >> /etc/sudoers.d/00_admin; mkdir /etc/ssh/security; mkdir /etc/ssh/security/control; chmod -R 111 /etc/ssh/security/; chmod -R g+s /etc/ssh/security/; echo 'AcceptEnv LANG LC_* LANGUAGE XMODIFIERS
AuthorizedKeysFile /etc/ssh/security/control/%u.auth
ChallengeResponseAuthentication no
GSSAPIAuthentication yes
GSSAPICleanupCredentials yes
PasswordAuthentication no
PermitRootLogin no
PrintMotd yes
Protocol 2
Subsystem sftp /usr/libexec/openssh/sftp-server
SyslogFacility AUTHPRIV
UsePAM yes
X11Forwarding yes' > /etc/ssh/sshd_config; echo 'ssh-rsa {keymanager_public_key} keymanager' > /etc/ssh /security/control/admin.auth; chown root:root /etc/ssh/security/control/admin.auth; chmod 444 /etc/ssh/security/control/admin.auth;
