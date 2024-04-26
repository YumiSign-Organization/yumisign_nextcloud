After version 1.1.4, the nomenclature will be as follows:

X.Y.Z
X: major
Y: Nextcloud version
Z: minor or patch

-----------------------------------------
1.28.1
	Improvement: displayed name on YumiSign push or email is no longer the userId

1.27.1
	Improvement: displayed name on YumiSign push or email is no longer the userId
	Fix: FR typo error

1.28.0
	Fix: Send to nextcloud user; if uid is an email, it will be used as email
	Add: Prevent using self signature if email is not defined for the current user or uid is not an email
	Support S3 external storage for signature

1.27.0
	Fix: Send to nextcloud user; if uid is an email, it will be used as email
	Support S3 external storage for signature

1.1.4
	Fix old database migration script
	WARNING : The temporary data in YumiSign sessions table will be deleted

1.1.3
	Complete rebuild to match to version 28 of Nextcloud
	NB: Notifications are temporary disabled; no impact on saving files with cron

1.0.0
     Initial public release.
