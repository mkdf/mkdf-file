create table if not exists file
(
    id                int auto_increment
        primary key,
    dataset_id        int           not null,
    title             varchar(64)   not null,
    description       text null,
    filename          varchar(64)   not null,
    filename_original varchar(64)   not null,
    file_type         varchar(1024)   null,
    file_size         int           null,
    date_created      datetime      null,
    date_modified     datetime      null,
    constraint file_dataset_id_fk
        foreign key (dataset_id) references dataset (id)
);

create index file_dataset_id_index
    on file (dataset_id);

