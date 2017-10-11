it:
	#

# -----------------------------------------------------------------------------------------
# PROJECT TASKS
# -----------------------------------------------------------------------------------------

deploy-local:
	ssh -t dev@site.itdc.ge "cd words && git fetch --all && git reset --hard origin/master && cd crawler && composer install"
