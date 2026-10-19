FROM alpine:3.20

ARG SPEEDTEST_VERSION=1.2.0
ARG TARGETARCH

RUN apk add --no-cache curl tzdata ca-certificates \
 && case "${TARGETARCH:-amd64}" in \
      amd64) ARCH=x86_64 ;; \
      arm64) ARCH=aarch64 ;; \
      arm)   ARCH=armhf ;; \
      386)   ARCH=i386 ;; \
      *) echo "Unsupported architecture: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
 && curl -fsSL "https://install.speedtest.net/app/cli/ookla-speedtest-${SPEEDTEST_VERSION}-linux-${ARCH}.tgz" \
    | tar -xz -C /usr/local/bin speedtest \
 && chmod +x /usr/local/bin/speedtest

COPY run-speedtest.sh entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/run-speedtest.sh /usr/local/bin/entrypoint.sh

VOLUME /data
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
