# Use a minimal Debian-based image for the runtime
FROM debian:11-slim

# Install necessary dependencies for running .NET
RUN apt-get update && apt-get install -y --no-install-recommends \
		libicu67 \
		git \
        libgdiplus \
        bash \
        procps \
        nano \
	;	

# Set the working directory in the container
WORKDIR /app

# Define a build argument for the source directory
ARG source_dir=.

# Copy the published .NET app into the container
COPY ${source_dir} /app

# Set executable permissions for the .NET app (if not already set)
RUN chmod +x /app/MSW

# Define the entrypoint to run the .NET app
ENTRYPOINT ["sh", "-c", "./MSW Port=$WATCHDOG_PORT"]
