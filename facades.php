<?php
    namespace Thin;

    Alias::facade('KVES',           'Es',                   'Kvs');
    Alias::facade('KVS',            'Mysql',                'Kvs');
    Alias::facade('KVLS',           'Lite',                 'Kvs');
    Alias::facade('Io',             'Client',               'ElephantIO');
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
    Alias::facade('Client',         'Client',               'Goutte');
    Alias::facade('Appdb',          'Manager',              'Illuminate\Database\Capsule');
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
