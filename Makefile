# @configure_input@

doc: composer-update
	./vendor/bin/apigen generate

composer-clean:
	rm -rf vendor/bandwidth-throttle/bandwidth-throttle/ composer.lock

composer-update: composer-clean
	composer.phar update
