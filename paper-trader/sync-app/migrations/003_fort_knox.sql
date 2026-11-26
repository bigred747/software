alter table device_tokens add column expires_at timestamptz;
alter table device_tokens add column revoked_at timestamptz;

create table if not exists sync_audit (
  id bigserial primary key,
  owner_sub text,
  action text not null,
  ok boolean not null,
  detail text not null default '',
  at timestamptz not null default now()
);

create index if not exists sync_audit_owner_at_idx on sync_audit (owner_sub, at desc);
