<?php

$wgMainCacheType = CACHE_MEMCACHED;
$wgMemCachedServers = array( "__MEMCACHED_HOST__:__MEMCACHED_PORT__" );

$wgSessionCacheType = CACHE_MEMCACHED;
# Turn this option back on if we use memcached
$wgUseDatabaseMessages = true;

$wgSessionsInObjectCache = true; # optional
$wgParserCacheType = CACHE_MEMCACHED; # optional
$wgMessageCacheType = CACHE_MEMCACHED; # optional
$wgLanguageConverterCacheType = CACHE_MEMCACHED;
$wgEnableSidebarCache = true;
$wgMiserMode = true;
$wgDisableCounter = true;
