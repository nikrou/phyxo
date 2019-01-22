Treflez
=======

Treflez is a simple, mobile-ready  responsive theme based on [Bootstrap](https://getbootstrap.com)

Requirements
------------

This project is build using [node](https://nodejs.org/en/)

### Components

-   [Bootstrap 4](https://getbootstrap.com)
-   [Bootswatch](https://bootswatch.com)
-   [PhotoSwipe](http://photoswipe.com/)
-   [Slick](http://kenwheeler.github.io/slick/)
-   [jQuery-Touch-Events](https://github.com/benmajor/jQuery-Touch-Events)

### Development & Customizing

-   All stylesheets are compiled from Bootstrap's Sass source files using node-sass.
-   Dependencies are managed using npm.
-   All javascript files are transpiled to ES2015 using babel.
-   To install build dependencies, use

```sh
$ npm ci
```

The build process is based on npm scripts and uses common shell functions, so it might not work on Windows.

To build everything in development mode, use:

```sh
$ npm start
```

And to build theme for production, use:

```sh
$ npm run build
```

Thanks
------

That theme is based on [Piwigo Bootstrap Darkroom](https://github.com/tkuther/piwigo-bootstrap-darkroom).
