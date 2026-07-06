CREATE TABLE tx_pipliobackend_word (
    uid int(11) NOT NULL auto_increment,
    pid int(11) NOT NULL DEFAULT 0,
    tstamp int(11) NOT NULL DEFAULT 0,
    crdate int(11) NOT NULL DEFAULT 0,
    deleted tinyint(4) NOT NULL DEFAULT 0,
    hidden tinyint(4) NOT NULL DEFAULT 0,
    topic varchar(50) NOT NULL DEFAULT '',
    difficulty varchar(10) NOT NULL DEFAULT 'easy',
    word varchar(255) NOT NULL DEFAULT '',
    artikel varchar(5) NOT NULL DEFAULT '',
    word_type varchar(20) NOT NULL DEFAULT '',
    is_nomen tinyint(1) NOT NULL DEFAULT 0,
    plural_form varchar(255) NOT NULL DEFAULT '',
    rhyme_words varchar(1000) NOT NULL DEFAULT '',
    no_rhyme_words varchar(1000) NOT NULL DEFAULT '',
    wrong_options varchar(1000) NOT NULL DEFAULT '',
    PRIMARY KEY (uid),
    KEY topic_difficulty (topic, difficulty),
    UNIQUE KEY topic_difficulty_word (topic, difficulty, word(191))
);

CREATE TABLE tx_pipliobackend_interest (
    uid int(11) NOT NULL auto_increment,
    pid int(11) NOT NULL DEFAULT 0,
    tstamp int(11) NOT NULL DEFAULT 0,
    crdate int(11) NOT NULL DEFAULT 0,
    deleted tinyint(4) NOT NULL DEFAULT 0,
    hidden tinyint(4) NOT NULL DEFAULT 0,
    name varchar(255) NOT NULL DEFAULT '',
    email varchar(255) NOT NULL DEFAULT '',
    page_title varchar(255) NOT NULL DEFAULT '',
    page_url varchar(2048) NOT NULL DEFAULT '',
    source_page_id int(11) NOT NULL DEFAULT 0,
    remote_address varchar(64) NOT NULL DEFAULT '',
    user_agent varchar(512) NOT NULL DEFAULT '',
    consent_timestamp int(11) NOT NULL DEFAULT 0,
    privacy_version varchar(32) NOT NULL DEFAULT 'v1',
    PRIMARY KEY (uid),
    KEY email (email(191)),
    KEY source_page_id (source_page_id),
    KEY remote_address_crdate (remote_address(32), crdate),
    KEY email_crdate (email(191), crdate)
);

CREATE TABLE tx_pipliobackend_package (
    uid int(11) NOT NULL auto_increment,
    pid int(11) NOT NULL DEFAULT 0,
    tstamp int(11) NOT NULL DEFAULT 0,
    crdate int(11) NOT NULL DEFAULT 0,
    deleted tinyint(4) NOT NULL DEFAULT 0,
    hidden tinyint(4) NOT NULL DEFAULT 0,
    package_id varchar(64) NOT NULL DEFAULT '',
    title varchar(255) NOT NULL DEFAULT '',
    description text,
    recommended_grade varchar(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (uid),
    UNIQUE KEY package_id (package_id)
);

CREATE TABLE tx_pipliobackend_topic (
    uid int(11) NOT NULL auto_increment,
    pid int(11) NOT NULL DEFAULT 0,
    tstamp int(11) NOT NULL DEFAULT 0,
    crdate int(11) NOT NULL DEFAULT 0,
    deleted tinyint(4) NOT NULL DEFAULT 0,
    hidden tinyint(4) NOT NULL DEFAULT 0,
    topic_id varchar(64) NOT NULL DEFAULT '',
    title varchar(255) NOT NULL DEFAULT '',
    subtitle varchar(255) NOT NULL DEFAULT '',
    color_key varchar(32) NOT NULL DEFAULT '',
    sort_order int(11) NOT NULL DEFAULT 0,
    package int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (uid),
    UNIQUE KEY topic_id (topic_id),
    KEY package (package)
);

CREATE TABLE tx_pipliobackend_graderecommendation (
    uid int(11) NOT NULL auto_increment,
    pid int(11) NOT NULL DEFAULT 0,
    tstamp int(11) NOT NULL DEFAULT 0,
    crdate int(11) NOT NULL DEFAULT 0,
    deleted tinyint(4) NOT NULL DEFAULT 0,
    hidden tinyint(4) NOT NULL DEFAULT 0,
    sorting int(11) NOT NULL DEFAULT 0,
    grade varchar(1) NOT NULL DEFAULT '1',
    package int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (uid),
    KEY grade (grade),
    KEY package (package)
);

CREATE TABLE tx_pipliobackend_badge (
    uid int(11) NOT NULL auto_increment,
    pid int(11) NOT NULL DEFAULT 0,
    tstamp int(11) NOT NULL DEFAULT 0,
    crdate int(11) NOT NULL DEFAULT 0,
    deleted tinyint(4) NOT NULL DEFAULT 0,
    hidden tinyint(4) NOT NULL DEFAULT 0,
    badge_id varchar(64) NOT NULL DEFAULT '',
    category varchar(20) NOT NULL DEFAULT 'milestone',
    title varchar(255) NOT NULL DEFAULT '',
    description text,
    icon varchar(32) NOT NULL DEFAULT '',
    xp_required int(11) DEFAULT NULL,
    streak_required int(11) DEFAULT NULL,
    total_sessions_required int(11) DEFAULT NULL,
    PRIMARY KEY (uid),
    UNIQUE KEY badge_id (badge_id)
);
