FROM php:8.1-zts-buster

LABEL maintainer "Jens Koehler <j.koehler@outlook.com>"

ARG DEBIAN_FRONTEND=noninteractive
ENV LC_ALL C

RUN apt-get clean && \
    apt-get -y update && \
    apt-get -y install ca-certificates supervisor

# Install additional tools
#RUN apt-get -y install netcat less man nano procps net-tools
RUN apt-get -y install nano iputils-ping

COPY supervisord.conf /etc/supervisor/supervisord.conf

RUN touch /supervisord.log /supervisord.pid && \
    chown www-data:www-data /supervisord.log && \
    chown www-data:www-data /supervisord.pid

RUN chown -R www-data:www-data /etc/supervisor
RUN chown -R www-data:www-data /var/log/supervisor

# Add Controller
RUN mkdir /controller
COPY ./_controller/ /controller
RUN chown -R www-data:www-data /controller

# Add Functions
RUN mkdir /functions
RUN chown -R www-data:www-data ./functions

# User: www-data
USER 33

EXPOSE 9001
#EXPOSE 8000

CMD ["/usr/bin/supervisord"]