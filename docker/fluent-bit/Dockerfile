# This Docker image contains Fluent Bit software (https://github.com/fluent/fluent-bit)
# Fluent Bit is licensed under Apache License 2.0.
# See repository for full license text:
#   https://raw.githubusercontent.com/fluent/fluent-bit/refs/heads/master/LICENSE
# The original license file is also included in /licenses/fluent-bit-LICENSE
#   Note that this is a copy of the original license as of December 2025 and may become outdated.
#   For the most current license, always refer to the upstream repository.
# No NOTICE file is present in the Fluent Bit repository as of December 2025.
# This image is not affiliated with or endorsed by the Fluent Bit or Treasure Data projects.
#
# With gratitude to the Fluent Bit authors and contributors for their open source efforts.
#
# This image is based on debian:trixie-slim (https://hub.docker.com/_/debian),
#   which is licensed under various open source licenses. See /usr/share/doc/*/copyright in the image for details.

# build a local image using the following command:
#   docker build -t docker-hub.mspchallenge.info/cradlewebmaster/fluent-bit:4.2.0 -f Dockerfile .

FROM debian:trixie-slim AS fluent_bit_builder
RUN apt-get update && apt-get install --no-install-recommends -y \
    bash \
    ca-certificates \
    curl \
    bison \
    cmake \
    flex \
    libyaml-dev \
    libssl-dev \
    pkg-config \
    build-essential \
    docker-cli
WORKDIR /tmp
RUN curl -L https://github.com/fluent/fluent-bit/archive/refs/tags/v4.2.0.tar.gz -o fluent-bit-src.tar.gz \
    && tar -xzf fluent-bit-src.tar.gz \
    && mv fluent-bit-4.2.0 fluent-bit-src
WORKDIR /tmp/fluent-bit-src/build
ARG CFLAGS="-v"
ENV CFLAGS=${CFLAGS}
# see all flags in https://raw.githubusercontent.com/fluent/fluent-bit/refs/heads/master/CMakeLists.txt, and
#   https://raw.githubusercontent.com/fluent/fluent-bit/12b191b5647e14d9adae28436ad4a50a3ea9dc43/cmake/plugins_options.cmake
RUN cmake ../ \
    -DFLB_RELEASE=On \
    -DFLB_EXAMPLES=Off \
    -DFLB_IN_EXEC=Off \
    -DFLB_SHARED_LIB=Off \
    -DFLB_KAFKA=Off \
    -DFLB_SMALL=On \
    && make \
    && make install

FROM debian:trixie-slim AS fluent_bit
LABEL org.opencontainers.image.title="Fluent Bit"
LABEL org.opencontainers.image.description="Unofficial Fluent Bit Docker image, built from source for MSP Challenge."
LABEL org.opencontainers.image.version="4.2.0"
LABEL org.opencontainers.image.licenses="Apache-2.0"
LABEL org.opencontainers.image.url="https://docker-hub.mspchallenge.info/cradlewebmaster/fluent-bit"
LABEL org.opencontainers.image.source="https://github.com/BredaUniversityResearch/MSPChallenge-Server/tree/dev/docker/fluent-bit"
LABEL org.opencontainers.image.authors="webmaster@cradle.buas.nl"
LABEL org.opencontainers.image.documentation="https://github.com/BredaUniversityResearch/MSPChallenge-Server/tree/dev/docker/fluent-bit/Dockerfile"
LABEL org.opencontainers.image.license_file="/licenses/fluent-bit-LICENSE"
LABEL org.opencontainers.image.license_url="https://raw.githubusercontent.com/fluent/fluent-bit/refs/heads/master/LICENSE"
COPY --from=fluent_bit_builder /usr/local/bin/fluent-bit /usr/bin/fluent-bit
COPY --from=fluent_bit_builder /usr/lib/x86_64-linux-gnu/libyaml-0.so.2 /usr/lib/x86_64-linux-gnu/libyaml-0.so.2
COPY --from=fluent_bit_builder /usr/bin/docker /usr/bin/docker
COPY docker-entrypoint.sh /fluent-bit/etc/docker-entrypoint.sh
COPY fluent-bit-LICENSE /licenses/fluent-bit-LICENSE
RUN chmod +x /fluent-bit/etc/docker-entrypoint.sh
ENTRYPOINT ["/fluent-bit/etc/docker-entrypoint.sh"]
