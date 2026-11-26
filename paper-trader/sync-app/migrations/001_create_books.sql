create table if not exists paper_books (
  desk_code text primary key,
  payload jsonb not null,
  markets integer not null default 0,
  updated_at timestamptz not null default now()
);
