create table if not exists paper_books_v2 (
  owner_sub text primary key,
  payload jsonb not null,
  markets integer not null default 0,
  updated_at timestamptz not null default now()
);

create table if not exists device_tokens (
  token_hash text primary key,
  owner_sub text not null,
  label text not null default 'computer',
  created_at timestamptz not null default now(),
  last_used_at timestamptz
);

create index if not exists device_tokens_owner_sub_idx on device_tokens (owner_sub);
