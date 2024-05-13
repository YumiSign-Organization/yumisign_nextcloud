After version 1.1.4, the nomenclature will be as follows:

X.Y.Z
X: major
Y: Nextcloud version
Z: minor or patch

-----------------------------------------
1.27.4
• Fix: migration issue for field "Mutex": using old Doctrine library
• Update: info.xml

1.29.1
• Fix: migration issue for field "Mutex" which throws Null Exception if YumiSign sessions exist in database
• Fix: graphic issue for field "Use proxy": check box could have a "no-Yes" and "no-No" value; no impact on functionality

1.28.3
• Fix: migration issue for field "Mutex" which throws Null Exception if YumiSign sessions exist in database
• Fix: graphic issue for field "Use proxy": check box could have a "no-Yes" and "no-No" value; no impact on functionality

1.27.3
• Fix: migration issue for field "Mutex" which throws Null Exception if YumiSign sessions exist in database

1.29.0
• Improvement: add Mutex on async Saving Signed Files process
• Fix: a String null exception occured when current user email was empty
• Fix: DB alias error on inner join (occured on PostgreSql engine)

1.28.2
• Improvement: add Mutex on async Saving Signed Files process
• Fix: a String null exception occured when current user email was empty
• Fix: DB alias error on inner join (occured on PostgreSql engine)

1.27.2
• Improvement: add Mutex on async Saving Signed Files process
• Fix: a String null exception occured when current user email was empty
• Fix: DB alias error on inner join (occured on PostgreSql engine)

1.28.1
• Improvement: displayed name on YumiSign push or email is no longer the userId

1.27.1
• Improvement: displayed name on YumiSign push or email is no longer the userId
• Fix: FR typo error

1.28.0
• Fix: Send to nextcloud user; if uid is an email, it will be used as email
• Add: Prevent using self signature if email is not defined for the current user or uid is not an email
• Support S3 external storage for signature

1.27.0
• Fix: Send to nextcloud user; if uid is an email, it will be used as email
• Support S3 external storage for signature

1.1.4
• Fix old database migration script
• WARNING : The temporary data in YumiSign sessions table will be deleted

1.1.3
• Complete rebuild to match to version 28 of Nextcloud
• NB: Notifications are temporary disabled; no impact on saving files with cron

1.0.0
     Initial public release.
