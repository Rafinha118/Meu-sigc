# Sistema Integrado de Gestão Comercial

## Objetivos
crie um site que vai fazer a gestão de estoque, gestão de RH, gestão de compra/vendas, organizar funções de cada colaborador, onde o administrador vai ter o histórico de auditoria de todos os setores, e dashboards financeiros e etc.

### Stack Tecnlógico
- Backend: PHP estruturado com sessões nativas
- Banco de dados: MySQL (PDO para segurança)
- Frontend: HTML5, PHP, CSS, Tailwind CSS

#### Regras de negócio (CORE)
Tratar senhas de usuários com hash bcrypt
O sistema deve ter uma página de históricos e manter sempre os logs de qualquer alteração feita por 
qualquer usuário, para auditorias futuras.
- Permitir que os usuários acessem ao sistema utilizando seus dados de acesso.
  
Ações do ator 

Resposta do sistema 

1 

Acessa a tela de login. 

Exibe os campos de CPF, nome completo e senha. 

2 

Preenche o CPF, nome completo e senha. 

Verifica os dados informados. 

3 

Solicita o acesso 

Valida os dados e identifica o perfil do usuário. 

4 



Libera o acesso às funções disponíveis para o perfil. 

- Registrar a saída de produtos do estoque.

Ações do ator 

Resposta do sistema 

1 

Seleciona o produto. 

Exibe os dados do produto. 

2 

Informa a quantidade. 

Verifica a disponibilidade em estoque. 

3 

Confirma a saída. 

Registra a saída do produto. 

4 

 

Atualiza a quantidade disponível no estoque. 


- Registra as vendas realizadas no 
supermercado.

Ações do ator 

Resposta do sistema

1

Seleciona os produtos. 

Exibe os produtos e seus preços.

2

Informa as quantidades.

Calcula o valor total da venda.

3

Informa a forma de pagamento.

Registra a venda.

4

Confirma a venda.

Atualiza o estoque e finaliza a venda.

- Permitir a consulta dos produtos e das quantidades disponíveis em estoque. 

Ações do ator 

Resposta do sistema 

1 

Acessa a área de produtos e estoque. 

Exibe a lista de produtos. 

2 

Pesquisa o produto. 

Localiza o produto. 

3 

Seleciona o produto. 

Exibe os dados e a quantidade disponíveis. 

4 

Consulta as informações. 

Mantém os dados disponíveis para visualização. 

- Permitir o acompanhamento e gerenciamento dos produtos em estoque. 

Ações do ator 

Resposta do sistema 

1 

Acesse a área de estoque. 

Exibe os produtos cadastrado. 

2 

Pesquisa ou seleciona um produto. 

Exibe a quantidade disponível. 

3 

Realiza uma alteração no estoque. 

Valida as informações. 

4 

Confirma a alteração. 

Atualiza os dados do estoque. 

- Permitir a análise dos relatórios de vendas de determinado período. 

Ações do ator 

Resposta do sistema 

1 

Acessa a área de relatórios. 

Exibe as opções de relatório. 

2 

Seleciona o período 

Pesquisa as vendas realizadas. 

3 

Solicita o relatório. 

Processa os dados das vendas. 

4 

Analisa as Informações. 

Exibe o relatório de vendas. 

- Permitir a consulta das informações dos fornecedores e dos produtos fornecidos. 

Ações do ator 

Resposta do sistema 

1 

Acessa a área de fornecedores. 

Exibe a lista de fornecedores. 

2 

Pesquisa o fornecedor. 

Localiza o fornecedor. 

3 

Seleciona o fornecedor. 

Exibe nome, telefone e e-mail. 

4 

Consulta os produtos fornecidos. 

Exibe os produtos relacionados ao fornecedor. 

- Permitir o acompanhamento das entradas e saídas financeiras do supermercado. 

Ações do ator 

Resposta do sistema 

1 

Acessa o fluxo de caixa. 

Exibe as movimentações financeiras. 

2 

Consulta as entradas e saídas. 

Calcula os valores. 

3 

Seleciona o período. 

Filtra as movimentações. 

4 

Analisa as informações. 

Exibe o saldo do período. 

- Permitir que o dono altere o preço dos produtos cadastrados. 

Ações do ator 

Resposta do sistema 

1 

Acessa a área de produtos. 

Exibe a lista de produtos. 

2 

Seleciona o produto. 

Exibe o preço atual. 

3 

Informa o novo preço. 

Valida o valor informado. 

4 

Confirma a alteração. 

Atualiza o preço do produto. 

- Permitir o cadastro de novos produtos no sistema. 

Ações do ator 

Resposta do sistema 

1 

Acesse a área de produtos. 

Exibe as opções disponíveis. 

2 

Seleciona a opção de adicionar produto. 

Exibe o formulário de cadastro. 

3 

Preenche os dados do produto. 

Valida as informações 

4 

Confirma o cadastro. 

Salva o novo produto no sistema. 

- Permitir a remoção de produtos cadastrados no sistema. 

Ações do ator 

Resposta do sistema 

1 

Acessa a área de produtos. 

Exibe a lista de produtos. 

2 

Seleciona o produto. 

Exibe os dados do produto. 

3 

Solicita a remoção. 

Solicita a confirmação. 

4 

Confirma a remoção. 

Remove o produto do sistema. 

- Permitir o acompanhamento do faturamento obtido com as vendas.

Ações do ator 

Resposta do sistema 

1 

Acessa a área de faturamento. 

Exibe os dados disponíveis. 

2 

Seleciona o período. 

Pesquisa as vendas realizadas. 

3 

Solicita o faturamento. 

Calcula o valor total. 

4 

Analisa as informações. 

Exibe o faturamento do período. 

##### Regras Globais
- quero que sempre use essa fonte https://fonts.google.com/specimen/Isometra?preview.script=Latn e manter a tipografia consistente em todas as telas do sistema
- Utilize a seguinte paleta de cores em todo o sistema Lee Market: azul-escuro #111184 como cor principal, amarelo #FFD32C para destaques e botões, branco #FFFFFF para fundos e preto #000000 para textos e ícones. Mantenha a identidade visual consistente em todas as páginas.
- Use sempre PDO para conexão e queries no MySQL para evitar SQL Injections
- Mantenha o código limpo e comente apenas logicas complexas.
- Separe os arquivos de forma lógica: um arquivo para conexão com a base (bd.php) e scripts de backend isolados e views em HTML5/PHP, nunca faça o sistema como um monolito, deixe sempre separados todas as regras para facilitar os futuros upgrades. Organize o código em módulos independentes, separando as funcionalidades de produtos, estoque, vendas, usuários e relatórios, conforme os requisitos do projeto. Evite duplicação de código e facilite futuras atualizações.
 - Estilize as telas em Tailwind de forma responsiva priorizando o MobileFrist, garantindo que tabelas, formulários, menus e painéis funcionem corretamente em telas pequenas.
 - Retorne sempre as mensagens de erros de forma claras na interface para o usuário (TOAST)
 - Sempre substitua as caixas de diálogo nativas do navegador por modais personalizados, mantendo a identidade visual do Lee Market.

