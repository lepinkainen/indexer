# Indexer

## USAGE:

  1) Untar the indexer:
       tar zxvf indexer-x.x.tar.gz
  2) Create the thumbnail directory in an appropriate location: 
       mkdir -m 777 thumbnails
     The thumbnail directory has to be writeable by the webserver
     process.
  3) $EDITOR index.php, and change the settings to your liking
  4) cd dir_to_index
  5) ln -s PATH/TO/INDEXER/index.php .
  6) Enjoy =)
