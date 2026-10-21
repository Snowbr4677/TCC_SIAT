# Assistência Técnica

Projeto simples em PHP com conexão ao MySQL e autenticação básica de login.

## Requisitos

- XAMPP instalado (opcional, se quiser rodar localmente)
- Docker Desktop instalado para execução portátil
- Git opcional

## Opção 1: Rodar localmente no XAMPP

1. Inicie o Apache e o MySQL no XAMPP.
2. Coloque a pasta do projeto em `C:\xampp\htdocs\assistencia_tecnica`.
3. Acesse `http://localhost/assistencia_tecnica/`.
4. Para testar a autenticação, acesse `http://localhost/assistencia_tecnica/login.php`.

## Opção 2: Rodar em qualquer computador usando Docker

1. Instale o Docker Desktop.
2. Abra o terminal na pasta do projeto.
3. Execute:

   docker compose up --build

4. Acesse:
   - Aplicação: `http://localhost`
   - phpMyAdmin: `http://localhost:8081`

5. Para o banco, as credenciais do container são:
   - Host: `db`
   - Usuário: `appuser`
   - Senha: `appsecret`
   - Banco: `assistencia_tecnica`

## Usuário padrão

- E-mail: `admin@assistencia.local`
- Senha: `admin123`

## Banco

O banco principal é `assistencia_tecnica`.

O script `banco/assistencia_tecnica.sql` cria as tabelas e o usuário administrador padrão.

Para a prévia do TCC, importe `banco/demo_seed.sql` pelo phpMyAdmin. A carga adiciona
somente registros fictícios, não apaga dados existentes e pode ser executada novamente
sem repetir os cadastros demonstrativos. Os registros de vendas, ordens e despesas
ficam distribuídos pelos últimos dez meses para preencher os relatórios da apresentação.
