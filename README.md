Overview
========

The goal is to provide a benchmark suite, testing something representative
of real-world situations. This suite also includes some unrealistic
microbenchmarks - comparing the results of these is fairly pointless, however
they can still be useful to profile, to find optimization opportunities that may
carry over to a real site.

This script sets up nginx, siege, and PHP5/PHP7/HHVM over FastCGI, over a TCP
socket. Configuration is as close to identical as possible.

The script will run 300 warmup requests, then as many requests as possible in 1
minute. Statistics are only collected for the second set of data.

Results
=======

We don't have anything to share yet - we want to standardize and document
how the interpreters are built/installed first.

Usage
=====

As a regular user:

    composer.phar install # see https://getcomposer.org/download/
    hhvm perf.php --wordpress --php5=/path/to/bin/php-cgi # also works with php7
    hhvm perf.php --wordpress --hhvm=/path/to/hhvm

Running with --hhvm gives some additional server-side statistics. It is usual
for HHVM to report more requests than siege - some frameworks do call-back
requests to the current webserver.

Batch Usage
===========

If you want to run multiple combinations:

    composer.phar install # see https://getcomposer.org/download
    hhvm batch-run.php < batch-run.json > batch-run-out.json

See batch-run.json.example to get an idea of how to create batch-run.json.

Requirements
============

- composer
- nginx
- siege
- unzip
- A mysql server on 127.0.0.1:3306
- hhvm

I've been using the current versions available from yum on Centos 6.3. HHVM is required
as this is written in Hack.

The Targets
===========

Toys
----

Unrealistic microbenchmarks. We do not care about these results - they're only
here to give a simple, quick target to test that the script works correctly.

'hello, world' is useful for profiling request handling.

Wordpress
---------

- Data comes from installing the demo-data-creator plugin (included) on a
  fresh install of Wordpress, and clicking 'generate data' in the admin panel a
  bunch of times.
- URLs file is based on traffic to hhvm.com - request ratios are:

  100: even spread over long tail of posts
  50: WP front page. This number is an estimate - we get ~ 90 to /, ~ 1 to
      /blog/. Assuming most wordpress sites don't have our magic front page, so
      taking a value roughly in the middle.
  40: RSS feed
  5: Some popular post
  5: Some other popular post
  3: Some other not quite as popular post


The long tail was generated with:

    <?php
      for ($i = 0; $i <= 52; ++$i) {
      printf("http://localhost:__HTTP_PORT__/?p=%d\n", mt_rand(1,52));
    }

  Ordering of the URLs file is courtesy of the unix 'shuf' command.

Drupal
------

Aims to be realistic. Demo data is from devel-generate,
provided by the devel module. Default values were used, except:

- Users were spread over the last year, rather than the last week
- New main menus and navigation menus were created
- New articles and pages were created, with up to 30 comments per content,
  spread over the last year instead of the last week

As well as the database dump, the static files generated by the above process
(user images, images embedded in content) have also been included.

SugarCRM
--------

The upstream installation script provides an option to create
demonstration data - this was used to create the database dump included here.

There are two unrealistic microbenchmarks:

- just the login page - the page with the username/password form.
  Added to confirm a
[reported issue](http://zsuraski.blogspot.com/2014/07/benchmarking-phpng.html).
- just the logged-in home page. Added to be a little more realistic than
  rendering the form, however we have no idea what a realistic request
  distribution would look like

Laravel
-------

Unrealistic microbenchmark: just the 'Laravel 5' page from an empty
installation.

Magento
-------

- Data is official Magento sample data, however because of its original size we have replaced all images with a compressed HHVM logo and removed all mp3 files.
- After importing sample data we use the Magento console installer to do the installation for us.
- URLs are a variety of different pages:
    - Homepage
    - Category page
    - CMS page
    - Quicksearch
    - Advanced search
    - Simple product
    - Product with options
    - Product reviews

Mediawiki
---------

The main page is the Barack Obama page from Wikipedia; this is based on the
WikiMedia foundation using it as a benchmark, and finding it fairly
representative of wikipedia. A few other pages (HHVM, talk, edit) are also
loaded to provide a slightly more rounded workload.

Profiling
=========

perf.php can keep the suite running indefinitely:

    hhvm perf.php --i-am-not-benchmarking --no-time-limit --wordpress --hhvm=$HHVM_BIN
  
You can then attach 'perf' or another profiler to the running HHVM or php-cgi process, once the 'benchmarking' 
phase has started.

Direct support (especially for XHProf) is planned, but not yet implemented.

Contributing
============

Please see [CONTRIBUTING.md](https://github.com/hhvm/oss-performance/blob/master/CONTRIBUTING.md) for details.
