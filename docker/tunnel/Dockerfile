FROM alpine:3.19.0

ARG TUNNEL_User=tunnel

# Instalar sudo
RUN apk add --no-cache sudo

# Agregar el nuevo usuario y grupo
RUN addgroup -g 1000 "${TUNNEL_User}" && \
    adduser -D -u 1000 -G "${TUNNEL_User}" "${TUNNEL_User}"

# Agregar el usuario al grupo sudoers
RUN echo "${TUNNEL_User} ALL=(ALL) ALL" > /etc/sudoers.d/${TUNNEL_User} && \
    chmod 0440 /etc/sudoers.d/${TUNNEL_User}

# Change to root user
USER root

#Update packages
RUN apk update

#Install openssh client
RUN apk add --no-cache openssh

# Cambiar al nuevo usuario
USER ${TUNNEL_User}

# Establecer el directorio de trabajo
WORKDIR /home/${TUNNEL_User}

#Adding tunnel directory
COPY aspen-discovery/docker/tunnel/tunnel.sh aspen-discovery/docker/tunnel/config /${TUNNEL_User}/.ssh/

#Add entrypoint
ENTRYPOINT ["/tunnel.sh"]
