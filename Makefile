run:
	# Start interactive apache-php container, remove it when done
	docker run -it --rm -p 8080:80 --name indexer -v "${PWD}":/var/www/html php:7.4-apache
