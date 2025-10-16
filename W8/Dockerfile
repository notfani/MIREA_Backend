FROM rustlang/rust:nightly AS builder
WORKDIR /build

ENV SQLX_OFFLINE=true

COPY Cargo.toml ./
RUN mkdir src && echo "fn main() {}" > src/main.rs
RUN cargo build --release

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
COPY Rocket.toml /app/Rocket.toml
CMD ["./pdf_storage"]