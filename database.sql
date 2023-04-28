DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS user_settings CASCADE;
create table users
(
    id bigint auto_increment not null,
    password varchar(255) null,
    email varchar(255) null,
    phone varchar(255) null,
    created_at timestamp null,
    updated_at timestamp null,
    constraint users_pk
        primary key (id)
);

create table user_settings
(
    id bigint auto_increment not null,
    user_id bigint not null,
    confirmation enum('sms', 'email', 'telegram') default 'sms' not null,
    created_at timestamp null,
    updated_at timestamp null,
    constraint user_settings_pk
        primary key (id)
);

create table user_code
(
    id bigint auto_increment not null,
    user_id bigint not null,
    code varchar(255) not null,
    created_at timestamp(0) null,
    updated_at timestamp(0) null,
    constraint user_code_pk
        primary key (id)
);

create unique index settings_user_id_uindex
    on settings (user_id);

create unique index user_code_id_uindex
    on settings (user_id);
