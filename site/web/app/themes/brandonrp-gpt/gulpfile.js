// Gulp 4 drop-in for Sage 8 (brandonrp-gpt)
// Emits: dist/styles, dist/scripts, dist/images, dist/fonts

const { src, dest, series, parallel, watch } = require('gulp');
const sass         = require('gulp-sass')(require('sass')); // Dart Sass
const postcss      = require('gulp-postcss');
const autoprefix   = require('autoprefixer');
const sourcemaps   = require('gulp-sourcemaps');
const plumber      = require('gulp-plumber');
const flatten      = require("gulp-flatten");
const concat       = require('gulp-concat');
const terser       = require('gulp-terser');
const through2     = require('through2');
const browserSync  = require('browser-sync').create();

const argv   = require('yargs').argv;
const isProd = !!argv.production || process.env.NODE_ENV === 'production';

const paths = {
  styles: {
    entry: 'assets/styles/main.scss',
    src:   'assets/styles/**/*.scss',
    dest:  'dist/styles',
    // If you rely on bower components for SCSS imports, keep this:
    include: ['assets/styles', 'bower_components']
  },
  scripts: {
    entry: 'assets/scripts/main.js',
    src:   'assets/scripts/**/*.js',
    dest:  'dist/scripts'
  },
  images: {
    src:  'assets/images/**/*',
    dest: 'dist/images'
  },
  fonts: {
    src:  'assets/fonts/**/*.{woff,woff2,ttf,otf,eot,svg}',
    dest: 'dist/fonts'
  }
};

// ---------- Tasks ----------
function styles() {
  return src(paths.styles.entry, { allowEmpty: true })
    .pipe(plumber({
      errorHandler: function(err) {
        console.error(err.toString());
        this.emit('end');
      }
    }))
    .pipe(!isProd ? sourcemaps.init() : noop())
    .pipe(
      sass.sync({
        precision: 10,
        includePaths: paths.styles.include, // 'assets/styles','bower_components'
        quietDeps: true,                    // hide deprecations from deps
        logger: require('sass').Logger.silent
      }).on('error', function(err) {
        sass.logError(err);
        this.emit('end'); // Ensure stream ends on error
      })
    )
    .pipe(postcss([autoprefix()]))
    .pipe(!isProd ? sourcemaps.write('.') : noop())
    .pipe(dest(paths.styles.dest));
}

function scripts() {
  // Ensure module files go first, then your initializer
  return src([
      'assets/scripts/src/**/*.js',  // modal.js, mobile-nav.js, etc.
      'assets/scripts/main.js'       // calls the modules
    ], { allowEmpty: true, sourcemaps: !isProd })
    .pipe(plumber({
      errorHandler: function(err) {
        console.error(err.toString());
        this.emit('end');
      }
    }))
    .pipe(concat('main.js'))
    .pipe(isProd ? terser() : through2.obj())
    .pipe(dest(paths.scripts.dest, { sourcemaps: '.' }));
}

function images() {
  return src(paths.images.src, { allowEmpty: true })
    .pipe(dest(paths.images.dest));
}

function fonts() {
  return src("assets/fonts/**/*.{woff,woff2,ttf,otf,eot}")
    .pipe(flatten())
    .pipe(dest("dist/fonts"));
}

function watcher() {
  // Add debouncing and ignore patterns to prevent resource exhaustion
  const watchOptions = {
    ignoreInitial: true,
    delay: 250, // Debounce file changes
    queue: true  // Process files sequentially
  };
  
  // Only watch specific directories, not entire trees
  watch(paths.styles.src, watchOptions, series(styles, function bsStyles(done) {
    // Inject updated CSS without full reload when possible
    browserSync.stream();
    done();
  }));

  watch(paths.scripts.src, watchOptions, series(scripts, function bsScripts(done) {
    // Reload browser when JS changes
    browserSync.reload();
    done();
  }));

  watch(paths.images.src, watchOptions, series(images, function bsImages(done) {
    browserSync.reload();
    done();
  }));
  
  // Limit font watching to specific subdirectories to avoid watching large node_modules
  watch("assets/fonts/Icons/**/*.{woff,woff2,ttf,otf,eot,svg}", watchOptions, series(fonts, function bsFonts(done) {
    browserSync.reload();
    done();
  }));

  // Reload on PHP/template changes
  watch(['{lib,templates}/**/*.php', '*.php'], watchOptions).on('change', function () {
    browserSync.reload();
  });
}

// BrowserSync dev server proxying your local Trellis / WP site
function serve(done) {
  browserSync.init({
    proxy: 'https://brandonrp.test', // match your HTTPS dev URL to avoid redirects
    https: true,                     // let Safari use https://localhost:3000
    files: ['dist/styles/**/*.css', 'dist/scripts/**/*.js'],
    snippetOptions: {
      // Match Sage 8 defaults: don't inject in wp-admin except admin-ajax
      whitelist: ['/wp-admin/admin-ajax.php'],
      blacklist: ['/wp-admin/**']
    },
    notify: true,
    open: false
  });
  done();
}

// tiny no-op helper for conditional pipes
function noop() { 
  const through = require('stream').Transform; 
  return new through({ transform(file, enc, cb){ cb(null, file); } }); 
}

// ---------- Exports ----------
exports.styles  = styles;
exports.scripts = scripts;
exports.images  = images;
exports.fonts   = fonts;
exports.watch   = watcher;

// Full dev task: build once, then start BrowserSync + watchers
const dev = series(
  parallel(styles, scripts, images, fonts),
  serve,
  watcher
);

exports.dev     = dev;
exports.default = parallel(styles, scripts, images, fonts);
