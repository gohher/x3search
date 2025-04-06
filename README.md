# LTAB search & LTAB Photo Gallery page.json Handler 
I have developed a search program for the Photo Gallery X3 CMS (https://www.photo.gallery/).
I am not a professional developer, so there may be some errors or a lack of expertise in the database, PHP code, and other areas.

The program has been tested and is working correctly with PHP 8.3 and MariaDB 10.18.


Notice.

pjindex, pages, image table Index Data Reset

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE images;
TRUNCATE TABLE pages;
TRUNCATE TABLE pjindex;
SET FOREIGN_KEY_CHECKS = 1;

/admin/update.php 
