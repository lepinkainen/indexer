# Indexer

A PHP-based indexer for Apache open directories

## USAGE:


  1) Check out the indexer `git clone https://github.com/lepinkainen/indexer.git`
  2) Create the thumbnail directory in an appropriate location: 
       mkdir -m 777 thumbnails
     The thumbnail directory has to be writeable by the webserver
     process.
  3) $EDITOR index.php, and change the settings to your liking
  4) cd dir_to_index
  5) ln -s PATH/TO/INDEXER/index.php .
  6) Enjoy =)


## Development

To run this locally you can use the php apache image by running this command from the indexer directory:

`docker run -it -p 80:80 --name indexer -v "$PWD":/var/www/html php:7.4-apache`
