# build a local image using the following command:
#   docker build -t docker-hub.mspchallenge.info/cradlewebmaster/msp-challenge-simulations:latest -f Dockerfile .

# Use a minimal Debian-based image for the runtime
FROM debian:11-slim

# Debian 11 (bullseye) is EOL: its live repos no longer refresh, causing
# "Release file ... is expired" errors, and archive.debian.org does not mirror
# the debian-security suite at all (404). So drop debian-security/updates and
# point the main repo at the archive instead.
RUN sed -i \
        -e '/debian-security/d' \
        -e '/bullseye-updates/d' \
        -e 's|deb.debian.org/debian|archive.debian.org/debian|g' \
        /etc/apt/sources.list \
    && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until

# Install necessary dependencies for running .NET
RUN apt-get update && apt-get install -y --no-install-recommends \
		libicu67 \
        libgdiplus \
        ca-certificates \
	;

# Set the working directory in the container
WORKDIR /app

# Define a build argument for the source directory
ARG source_dir=.

# Copy the published .NET app into the container
COPY ${source_dir} /app

# Set executable permissions for the .NET app (if not already set)
RUN chmod +x /app/MSW
RUN chmod +x /app/CEL
RUN chmod +x /app/SEL
RUN chmod +x /app/REL
RUN chmod +x /app/MEL

# Define the entrypoint to run the .NET app
ENTRYPOINT ["sh", "-c", "./MSW Port=$WATCHDOG_PORT"]
