FROM php:8.2-cli-alpine

WORKDIR /app

COPY public/ /app/public/
COPY entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/entrypoint.sh && mkdir -p /data /data/uploads

ENV DATA_DIR=/data
ENV UPLOAD_DIR=/data/uploads

EXPOSE 8080

CMD ["/app/entrypoint.sh"]
