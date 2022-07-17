# Static Build
Base module that enables building static sites from data exported by
Static Export module.

## INTRODUCTION ##
This module serves as an orchestrator for the process of building a site,
and should be complemented with a compatible "static builder" module.

At this moment, you can install any of the following static builders:
* [Gatsby](https://www.drupal.org/project/static_builder_gatsby) (runs locally
  on your server)
* [AWS Codebuild](https://www.drupal.org/project/static_builder_codebuild)
  (runs on a CI/CD service)

## RUNNING A BUILD ##
This module and its builders work by spawning a background process which
actually runs a build command on a bash shell. That build command is defined
by the builder of your choice.

That process is executed by the user running the web server (usually `www-data`
or similar) so you must ensure that user can run the build command (that
means having that command installed and available in `www-data` user's `$PATH`
environment variable).

Once your site is built, it can be:
* Served by your own server: 1) use a separate domain for your site, or 2) add
some rewrite rules to your web server. Find out more on this at the end of
this document.
* Deployed to any external CDN/hosting service: use
[Static Deploy](https://www.drupal.org/project/static_suite) module.

## ADDING CUSTOM BUILDERS ##
Static builders are plugins that must follow these rules:
* Be annotated with @StaticBuilder annotation
* Implement Drupal\static_build\Plugin\StaticBuilderInterface
interface.
* Declare a route for editing configuration, named after this rule:
static_builder_{PLUGIN_ID}.settings

Example (static_builder_{PLUGIN_ID}.routing.yml):
```
static_builder_{PLUGIN_ID}.settings:
path: '/admin/config/static/build/{PLUGIN_ID}'
defaults:
  _form: \Drupal\static_builder_{PLUGIN_ID}\Form\SettingsForm
  _title: 'Static Builder - {PLUGIN_ID}: Settings'
requirements:
  _permission: 'administer site configuration'
```

## INSTALLATION ##
Run `composer require drupal/static_build`

## REQUIREMENTS ##
`rsync` is required to perform various I/O operations. It must be executable
by the user running your web server (usually "www-data".)

## CONFIGURATION ##
Configuration available at /admin/config/static/build.

## SERVING YOUR SITE FROM YOUR WEB SERVER ##
After your site is built, it's placed at
 `[BASE_DIRECTORY]/[BUILDER_ID]/live/current`

There are two possible ways of serving it from your own web server:
* Use a domain for Drupal and another separate one for serving your static
site: configure the document root of your virtual host to point to
`[BASE_DIRECTORY]/[BUILDER_ID]/live/current`
* Use the same domain for Drupal and your static site: configure a set of
rewrite rules to serve some routes from Drupal, and other ones from the
 filesystem.

This is a working example of "Rewrite Rules" for Apache and Gatsby (tweak it
to your needs) where we check if a path exists in the filesystem before
routing it to Drupal:

```
  ######### GATSBY CONFIG START ########

  # Due to security filters applied by Drupal's .htaccess,
  # do not pass any known Drupal directory to Gatsby,
  # and only allow extensions present on Gatsby

  # 1) Avoid access to /index.html files
  RewriteCond %{REQUEST_FILENAME} !^/(core|modules|sites|themes|profiles|libraries)/
  RewriteCond %{REQUEST_FILENAME} /index\.html$
  RewriteCond /var/www/my-site.com/drupal/docroot%{REQUEST_FILENAME} !-f
  RewriteCond /var/www/my-site.com/gatsby/live/current%{REQUEST_FILENAME} -f
  RewriteRule ^ - [R=404,L]

  # 2) Serve static files from filesystem
  RewriteCond %{REQUEST_FILENAME} !^/(core|modules|sites|themes|profiles|libraries)/
  RewriteCond %{REQUEST_FILENAME} .+\.(css|eot|gif|html|xml|txt|ico|jpe?g|js|json|map|png|svg|ttf|webmanifest|woff2?)$
  RewriteCond /var/www/my-site.com/drupal/docroot%{REQUEST_FILENAME} !-f
  RewriteCond /var/www/my-site.com/gatsby/live/current%{REQUEST_FILENAME} -f
  RewriteRule ^ /var/www/my-site.com/gatsby/live/current%{REQUEST_FILENAME} [L]

  # 3) Avoid access to directories with a trailing slash
  # This should be enabled with care: "manual" pages located at src/pages/
  # create a directory with the name of the file plus a index.html file
  # (e.g., src/pages/home.js creates public/home/index.html). Those
  # pages behave different from pages created with createPages() method
  # and even if we access them using "/home", they change their url
  # to "/home/", thus giving a 404 after a browser reload.
  # Disable this if you need to add "manual" pages,
  # or try "gatsby-plugin-remove-trailing-slashes" or
  # "gatsby-plugin-force-trailing-slashes" plugins.
  RewriteCond %{REQUEST_FILENAME} !^/(core|modules|sites|themes|profiles|libraries)/
  RewriteCond %{REQUEST_FILENAME} .+/$
  RewriteCond /var/www/my-site.com/drupal/docroot%{REQUEST_FILENAME} !-d
  RewriteCond /var/www/my-site.com/gatsby/live/current%{REQUEST_FILENAME} -d
  RewriteCond /var/www/my-site.com/gatsby/live/current%{REQUEST_FILENAME}/index.html -f
  RewriteRule ^ - [R=404,L]
  # Otherwise, if you want to remove the trailing slash, comment the above rule
  # and uncomment this one
  # RewriteRule ^(.*)/$ /$1 [R=301,L]

  # 4) Serve homepage
  # This is a special case, because "/" always exists on Drupal's side,
  # thus it's never passing through Gatsby. When your homepage is migrated to
  # Gatsby, you need to enable this rewrite.
  RewriteCond %{REQUEST_FILENAME} ^/$
  RewriteCond /var/www/my-site.com/gatsby/live/current/index.html -f
  RewriteRule ^ /var/www/my-site.com/gatsby/live/current/index.html [L]

  # 5) Serve directories (pretty-urls for contents)
  RewriteCond %{REQUEST_FILENAME} !^/(core|modules|sites|themes|profiles|libraries)/
  RewriteCond /var/www/my-site.com/drupal/docroot%{REQUEST_FILENAME} !-d
  RewriteCond /var/www/my-site.com/gatsby/live/current%{REQUEST_FILENAME} -d
  RewriteCond /var/www/my-site.com/gatsby/live/current%{REQUEST_FILENAME}/index.html -f
  RewriteRule ^ /var/www/my-site.com/gatsby/live/current%{REQUEST_FILENAME}/index.html [L]

  # 6) Set X-Generator header conditionally
  Header set X-Generator "Gatsby" "expr=%{REQUEST_FILENAME}=~m#/gatsby/#"

  <Directory "/var/www/my-site.com/gatsby/live/current">
    AllowOverride All
    Options -Indexes +FollowSymLinks
  </Directory>

  ######### GATSBY CONFIG END ########
```
