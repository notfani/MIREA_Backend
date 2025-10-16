# ---------- 1. стадия сборки ----------
FROM rust:1.78 AS builder
WORKDIR /build

# 1. копируем только Cargo.toml
COPY Cargo.toml ./

# 2. СРАЗУ создаём заглушку-файл, чтобы у Cargo появилась цель
RUN mkdir src && echo "fn main() {}" > src/main.rs

# 3. теперь можно генерировать lock и кэшировать зависимости
RUN cargo build --release
# (cargo сам создаст Cargo.lock при первом build)

# 4. копируем настоящий код и пересобираем
COPY src ./src
COPY templates ./templates
RUN touch src/main.rs && cargo build --release


FROM debian:13-slim
RUN apt-get update \
    && apt-get install -y libssl3 ca-certificates \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=builder /build/target/release/pdf_storage /app/pdf_storage
COPY templates /app/templates
# uploads появится автоматически (монтируется)
CMD ["./pdf_storage"]