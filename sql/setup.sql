create table if not exists file
(
    id            int default 0 not null
        primary key,
    dataset_id    int           not null,
    title         varchar(64)   not null,
    description   varchar(1024)  null,
    filename      varchar(64)   not null,
    location      varchar(64)   not null,
    date_created  datetime      null,
    date_modified datetime      null,
    constraint file_dataset_id_fk
        foreign key (dataset_id) references dataset (id)
);

create index file_dataset_id_index
    on file (dataset_id);