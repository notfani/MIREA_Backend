# ---------- 1. стадия сборки ----------
FROM rust:1.75 AS builder
WORKDIR /build

# кэшируем зависимости
COPY Cargo.toml Cargo.lock ./
RUN mkdir src && echo "fn main() {}" > src/main.rs
RUN cargo build --release
# теперь копируем настоящий код
COPY src ./src
COPY templates ./templates
# пересобираем с реальным main.rs
RUN touch src/main.rs && cargo build --release

# ---------- 2. финальный образ ----------
FROM debian:bookworm-slim
RUN apt-get update \
    && apt-get install -y libssl3 ca-certificates \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=builder /build/target/release/pdf_storage /app/pdf_storage
COPY templates /app/templates
# uploads появится автоматически (монтируется)
CMD ["./pdf_storage"]