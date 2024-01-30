let gulp = require('gulp'),
  sass = require('gulp-sass'),
  watch = require('gulp-watch'),
  autoprefixer = require('gulp-autoprefixer'),
  sassGlob = require('gulp-sass-glob'),
  scsslint = require('gulp-scss-lint'),
  browserSync = require('browser-sync').create(),
  sassUnicode = require('gulp-sass-unicode'); // @see: https://github.com/sass/sass/issues/1395

let config = {
  css: {
    src: ['../src/styles/*.scss', '../src/styles/**/*.scss'],
    dest: '../dist/',
  },
  sync: {
    proxy: 'http://irpauto.loc',
    port: '80',
    watch: '../dist'
  }
};

gulp.task('sass', function () {
  return gulp.src(config.css.src)
    // .pipe(sassGlob())
    // .pipe(scsslint())
    .pipe(sass().on('error', sass.logError))
    .pipe(autoprefixer({
      cascade: false
    }))
    .pipe(sassUnicode())
    .pipe(gulp.dest(config.css.dest))
    .pipe(browserSync.reload({stream: true}));
});

gulp.task('browser-sync', function () {
  browserSync.init({
    proxy: config.sync.proxy,
    port: config.sync.port,
  });
});

gulp.task('default', function () {
  gulp.watch(config.sync.watch, gulp.series('browser-sync'));
  gulp.watch(config.css.src, gulp.series('sass'));
});
