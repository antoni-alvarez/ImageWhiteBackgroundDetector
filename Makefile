start: up clear ssh

clear:
	docker compose -f devops/docker-compose.yaml exec php bin/console c:c

up:
	docker compose -f devops/docker-compose.yaml build --pull && \
	docker compose -f devops/docker-compose.yaml up -d --force-recreate

stop:
	docker compose -f devops/docker-compose.yaml down

ssh:
	docker compose -f devops/docker-compose.yaml exec php sh