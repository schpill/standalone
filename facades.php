<?php
    namespace Thin;

    Alias::facade('KVES',           'Es',                   'Kvs');
    Alias::facade('KVS',            'Mysql',                'Kvs');
    Alias::facade('KVLS',           'Lite',                 'Kvs');
    Alias::facade('Io',             'Client',               'ElephantIO');
    Alias::facade('ZLCache',        'Cache',                'Zelift');
    Alias::facade('ZLLog',          'Log',                  'Zelift');
    Alias::facade('ZLModel',        'Model',                'Zelift');
    Alias::facade('ZLFile',         'File',                 'Zelift');
    Alias::facade('Sess',           'Sessionstore',         'Thin');
    Alias::facade('Em',             'Entity',               'Dbjson');
    Alias::facade('Model',          'Entity',               'Dbredis');
    Alias::facade('Raw',            'Entity',               'Raw');
    Alias::facade('N0sql',          'Entity',               'Nosql');
    Alias::facade('Fs',             'Entity',               'S3');
    Alias::facade('RawSchema',      'Schema',               'Raw');
    Alias::facade('Way',            'Entity',               'Way');
    Alias::facade('Dm',             'Querystatic',          'Raw');
    Alias::facade('Blazz',          'Entity',               'Blazz');
    Alias::facade('Redy',           'Entity',               'Fast');
    Alias::facade('Q',              'Query',                'Dbredis');
    Alias::facade('R',              'Entity',               'Dbredis');
    Alias::facade('Red',            'Db',                   'Dbredis');
    Alias::facade('Ev',             'Manager',              'Phalcon\\Events');
    Alias::facade('L',              'File',                 'Phalcon\\Logger\\Adapter');
    Alias::facade('Res',            'Http\\Response',       'Phalcon');
    Alias::facade('MVCController',  'Mvc\\Controller',      'Phalcon');
    Alias::facade('MVCApp',         'Mvc\\Application',     'Phalcon');
    Alias::facade('MVCView',        'Mvc\\View',            'Phalcon');
    Alias::facade('C',              'Mvc\\Collection',      'Phalcon');
    Alias::facade('AclModel',       'Acl',                  'Phalcon');
    Alias::facade('AppEx',          'Exception',            'Phalcon');
    Alias::facade('AclDb',          'Memory',               'Phalcon\\Acl\\Adapter');
    Alias::facade('AclRole',        'Role',                 'Phalcon\\Acl');
    Alias::facade('AclResource',    'Resource',             'Phalcon\\Acl');
    Alias::facade('ViewPhp',        'Php',                  'Phalcon\Mvc\View\Engine');
    Alias::facade('ViewVolt',       'Volt',                 'Phalcon\Mvc\View\Engine');
    Alias::facade('MVCDi',          'FactoryDefault',       'Phalcon\DI');
    Alias::facade('Datation',       'Carbon',               'Carbon');
    Alias::facade('I',              'Inflector',            'Thin');
    Alias::facade('A',              'Arrays',               'Thin');
    Alias::facade('F',              'File',                 'Thin');
    Alias::facade('U',              'Utils',                'Thin');
    Alias::facade('S',              'Session',              'Thin');
    Alias::facade('MyOrm',          'Model',                'Illuminate\Database\Eloquent');
    Alias::facade('Cursor',         'Cursor',               'Fast');
    Alias::facade('Flaty',          'Entity',               'Dbfile');
    Alias::facade('Keep',           'Entity',               'Dbphp');
    Alias::facade('Kit',            'Entity',               'Live');
    Alias::facade('My',             'Entity',               'My');
    Alias::facade('Light',          'Entity',               'Dblight');
    Alias::facade('Mdo',            'Entity',               'Mdo');
    Alias::facade('Mlite',          'Entity',               'Mlite');
    Alias::facade('Ql',             'Entity',               'Dbsql');
    Alias::facade('Clue',           'Entity',               'Clue');
    Alias::facade('Flight',         'Cache',                'Dblight');
    Alias::facade('Save',           'Staticstore',          'Raw');
    Alias::facade('Kh',             'Caching',              'Dbfile');
    Alias::facade('Ks',             'Entity',               'Keystore');
    Alias::facade('Ksc',            'Caching',              'Keystore');
    Alias::facade('Mdb',            'Monga',                'League');
    Alias::facade('Geos',           'Geotools\\Geotools',   'League');
    Alias::facade('Fake',           'Factory',              'Faker');
    Alias::facade('Live',           'Boris',                'Boris');
    Alias::facade('Marksdown',      'CommonMarkConverter',  'League\CommonMark');
    Alias::facade('Crontab',        'CronExpression',       'Cron');
    Alias::facade('Maily',          'Server',               'Ddeboer\Imap');
    Alias::facade('Client',         'Client',               'Goutte');
    Alias::facade('Finder',         'Finder',               'Symfony\Component\Finder');
    Alias::facade('Cloud',          'Store',                'S3');

    lib('ioc');
    Alias::facade('ZeApp',          'IocLib',               'Thin');

    lib('forever');
    Alias::facade('Own',            'ForeverLib',           'Thin');

    lib('myconfstatic');
    Alias::facade('Myconf',         'MyconfstaticLib',      'Thin');

    lib('nowstatic');
    Alias::facade('Now',            'NowstaticLib',         'Thin');
    Alias::facade('Eventy',         'NowstaticLib',         'Thin');

    lib('me');
    Alias::facade('Me',             'MeLib',                'Thin');

    lib('translate');
    lib('translatestatic');
    Alias::facade('T',              'TranslatestaticLib',   'Thin');

    Alias::facade('Appdb', 'Manager', 'Illuminate\Database\Capsule');
    Alias::facade('BindingResolutionException', 'BindingResolutionException', 'Illuminate\Container');
