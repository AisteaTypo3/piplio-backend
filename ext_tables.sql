CREATE TABLE tx_pipliobackend_word (
    topic varchar(50) NOT NULL DEFAULT '',
    difficulty varchar(10) NOT NULL DEFAULT 'easy',
    word varchar(255) NOT NULL DEFAULT '',
    artikel varchar(5) NOT NULL DEFAULT '',
    word_type varchar(20) NOT NULL DEFAULT '',
    is_nomen tinyint(1) NOT NULL DEFAULT 0,
    plural_form varchar(255) NOT NULL DEFAULT '',
    rhyme_words varchar(1000) NOT NULL DEFAULT '',
    no_rhyme_words varchar(1000) NOT NULL DEFAULT '',
    wrong_options varchar(1000) NOT NULL DEFAULT ''
);
