var elixir = require('laravel-elixir');

/*
 |--------------------------------------------------------------------------
 | Elixir Asset Management
 |--------------------------------------------------------------------------
 |
 | Elixir provides a clean, fluent API for defining some basic Gulp tasks
 | for your Laravel application. By default, we are compiling the Sass
 | file for our application, as well as publishing vendor resources.
 |
 */

var bowerDir = './vendor/bower_components/';
var paths = {
    jquery: bowerDir + 'jquery/',
    bootstrap: bowerDir + 'bootstrap-sass-official/assets/',
    fontawesome: bowerDir + 'fontawesome/'
};

elixir.config.sourcemaps = false;
elixir(function (mix) {
    // css
    mix.sass('style.scss', 'public/css/style.css', {
        includePaths: [
            paths.bootstrap + 'stylesheets/'
            , paths.fontawesome + 'scss/'
        ]
        , style: 'expanded'
        , precision: 10
    })
        .sass('index.scss', 'public/css/index.css', {
            includePaths: [
                paths.bootstrap + 'stylesheets/'
            ]
        })

        .sass('admin.scss', 'public/css/admin.css')

        // fonts
        .copy([
            paths.fontawesome + 'fonts/**'
        ], 'public/fonts')

        // scripts
        .copy([
            paths.bootstrap + 'javascripts/bootstrap.min.js'
            , paths.jquery + 'dist/jquery.min.js'
        ], 'public/js/')

        // blueimp-file-upload
        .copy([bowerDir + 'blueimp-file-upload/**'], 'public/js/lib/jquery/blueimp-file-upload')

        // cropper
        .copy([bowerDir + 'cropper/dist/**'], 'public/js/lib/jquery/cropper')

        // js-cookie
        .copy([bowerDir + 'js-cookie/src/**'], 'public/js')

        // jquery-placeholder
        .copy([bowerDir + 'jquery-placeholder/**'], 'public/js/lib/jquery/placeholder')


        // version
        //.version(['css/style.css'])
    ;
});
