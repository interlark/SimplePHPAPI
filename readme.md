# Simple PHP API Project [API]

With this API you use the following methods:
  - Register new user in the system
  - Upload any file
  - Get file
  - Update file
  - Get file metadata

You can also store your files on the server compressed.

For the implementation I used [Slim 3][slim3] to easely make routs and methods for this little api and [Eloquent ORM][eloquent-orm] to work with a database.

### Installation

Make sure you have installled apache and mysql (or other dbms), so you can start these cervices right now if they were stopped. Go, for example, to your home directory and copy the project.

```sh
$ mkdir ~/www/api.local
$ cd ~/www/api.local
$ git clone https://github.com/interlark/SimplePHPAPI
```

Folder **files** would be our filestore, so we need to make it accessable for the apache. (For the user ***www-data***) :
```sh
$ sudo chmod 777 files
```

The next step of our installation will be: choosing you database (I suggest using MySQL DBMS), creating new schema ***db_api_project*** and import the dump I leaved for you - ***"db_api_project.sql"***. I'm sure you can easily get this step.

After that you have to configure apache for this project. You know, the API works through HTTPS, and traffic between you and server is compressed. So make sure you enabled ***mod_rewrite***, ***mod_deflate***, ***mod_ssl*** for the server.

First of all, we want user to comfortably redirected to https protocol, if he tried to use methids via http. Let's configure the http site version first. Create configuration and enable the site.

```sh
sudo su
cd /etc/apache2/sites-available
touch api_project.conf
```
***xsolla.conf***:
```
<VirtualHost *:80>
	ServerName api.local
	ServerAdmin webmaster@localhost
	ServerAlias www.api.local
	DocumentRoot /home/<user>/www/api.local/public
	LogLevel info ssl:warn
	ErrorLog /home/<user>/www/api.local/logs/error.log
	CustomLog /home/<user>/www/api.local/logs/access.log combined
	<Directory /home/<user>/www/api.local/public>
		Require all granted
		RewriteEngine On
		RewriteCond %{HTTPS} off
		RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI}
	</Directory>
</VirtualHost>

# vim: syntax=apache ts=4 sw=4 sts=4 sr noet
```
Where <user> should be your username.

And after we done this configuratinon let's enable the site on 80 port:
```sh
sudo a2ensite api_project.conf
```
So now we gonna create configuration for https site - our api. And with mod_rewrite enable Slim 3 routs :

***api_project-ssl.conf***:
```
<IfModule mod_ssl.c>
	<VirtualHost _default_:443>
		ServerName api.local
		ServerAdmin webmaster@localhost
		ServerAlias www.api.local
		DocumentRoot /home/<user>/www/api.local/public_ssl
		LogLevel info ssl:warn
		ErrorLog /home/<user>/www/api.local/logs/error_ssl.log
		CustomLog /home/<user>/www/api.local/logs/access_ssl.log combined

		SSLEngine on
		SSLCertificateFile	/etc/ssl/certs/api_server.pem
		SSLCertificateKeyFile /etc/ssl/private/api_server.key

		<Directory /home/<user>/www/api.local/public_ssl>
    		Require all granted
			RewriteEngine on
			RewriteCond %{REQUEST_FILENAME} !-d
			RewriteCond %{REQUEST_FILENAME} !-f
			RewriteRule . index.php [L]
        </Directory>

		BrowserMatch "MSIE [2-6]" \
				nokeepalive ssl-unclean-shutdown \
				downgrade-1.0 force-response-1.0
	</VirtualHost>
</IfModule>

# vim: syntax=apache ts=4 sw=4 sts=4 sr noet
```

Oh yeah, I forgot about ***api_server.pem*** and ***api_server.key***, to install certification and it's key run the script /ssl/install.sh with root privileges.

Using self-signed certificates, of course, can protect against passive listening and sniffing, but it still does not guarantee customers that it is the real server.

Well it's time to enable our https site and restart the server, and don't forget to add ```127.0.0.1 api.local``` to your /etc/hosts file.

```sh
sudo a2ensite api_project-ssl.conf
sudo service apache2 restart
```

### Configuration
Connection to your database you can set up in /app/init.php file:
```
[
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'db_api_project',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'collation' => 'utf8_general_ci',
    'prefix' => ''
]
```
For more information please visit official [documentation][eloquendt-db].
### API Methods

- Registration (POST api.local/register?name=\<username\>&pass=\<password\> HTTP/1.1)

If the registration would be successful, method returns unsafe constant apikey (to get more security write down a login method to obtain apikey associated with session). With this apikey you can work with other methods, just add the header ```Authentication: <apikey>```.

- Upload a file (POST api.local/upload HTTP/1.1)

This method can upload your file in the datastore, the file can even be compressed, all you need us just add the header ```compress: true```.

- Update the file (POST api.local/update HTTP/1.1)

Method updates file from the datastore with the file from the post header. But make sure that you are the owner of the file, otherwise you'd get access error.

- Get file (GET api.local/getfile?filename=\<filename\> HTTP/1.1)

Method returns file from the datastore.

- Get file list (GET api.local/getfilelist HTTP/1.1)

Method returns list of all available files in the datastore.

- Get file metadata (GET api.local/getfilemetadata?filename=\<filename\> HTTP/1.1)

Method returns metadata of the file.

### Test
And actually there is one more method that test the API:

- Test API (GET api.local/testapi?username=\<username\>&password=\<password\> HTTP/1.1)

Method register new user with the name and password and using it test all methids. The output is html file with all details. (Saved one you can find in /test_api/dump).

To edit output, you can edit /app/views/test.twig

To use view with twig template, composer got next requirement:
```
"slim/twig-view": "^2.1"
```
### Version
1.0.0
### Tech
To realise the API I used slim php framework, larvel's eloquent orm, slim twig

License
----

MIT

[//]: # (These are reference links used in the body of this note and get stripped out when the markdown processor does its job. There is no need to format nicely because it shouldn't be seen. Thanks SO - http://stackoverflow.com/questions/4823468/store-comments-in-markdown-syntax)

   [slim3]: <http://www.slimframework.com>
   [eloquent-orm]: <https://github.com/illuminate/database>
   [eloquendt-db]: <https://laravel.com/docs/5.1/database>


