# build a local image using the following command:
#   docker build -t docker-hub.mspchallenge.info/cradlewebmaster/fluent-bit:4.2.0 -f Dockerfile .

FROM debian:trixie-slim AS fluent_bit_builder
RUN apt-get update && apt-get install -y \
    bash \
    curl \
    bison \
    cmake \
    flex \
    libyaml-dev \
    libssl-dev \
    pkg-config \
    build-essential
WORKDIR /tmp
RUN curl -L https://github.com/fluent/fluent-bit/archive/refs/tags/v4.2.0.tar.gz -o fluent-bit-src.tar.gz \
    && tar -xzf fluent-bit-src.tar.gz \
    && mv fluent-bit-4.2.0 fluent-bit-src
WORKDIR /tmp/fluent-bit-src/build
RUN cmake ../ \
    && make \
    && make install

FROM debian:trixie-slim AS fluent_bit
RUN apt-get update && apt-get install -y docker.io bash libyaml-0-2
COPY --from=fluent_bit_builder /usr/local/bin/fluent-bit /usr/bin/fluent-bit
COPY docker-entrypoint.sh /fluent-bit/etc/docker-entrypoint.sh
RUN chmod +x /fluent-bit/etc/docker-entrypoint.sh
ENTRYPOINT ["/fluent-bit/etc/docker-entrypoint.sh"]
