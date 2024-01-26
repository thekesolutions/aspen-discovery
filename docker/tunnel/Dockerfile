FROM alpine:3.19.0

# Instalar sudo
RUN apk add --no-cache sudo

# Agregar el nuevo usuario y grupo
RUN addgroup -g 1000 "${TUNNEL_User}" && \
    adduser -D -u 1000 -G "${TUNNEL_User}" "${TUNNEL_User}"

# Agregar el usuario al grupo sudoers
RUN echo "${TUNNEL_User} ALL=(ALL) ALL" > /etc/sudoers.d/${TUNNEL_User} && \
    chmod 0440 /etc/sudoers.d/${TUNNEL_User}

# Cambiar al nuevo usuario
USER ${TUNNEL_User}

# Establecer el directorio de trabajo
WORKDIR /home/${TUNNEL_User}

#Install openssh client
RUN apk add --update --no-cache openssh

#Adding tunnel directory
COPY tunnel.sh config /${TUNNEL_User}/.ssh/

#Add entrypoint
ENTRYPOINT ["/tunnel.sh"]
