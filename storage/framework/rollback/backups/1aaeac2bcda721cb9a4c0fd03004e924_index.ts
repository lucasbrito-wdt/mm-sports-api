export default [
  {
    title: 'Home',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
  },
  {
    title: 'Usuários',
    icon: { icon: 'tabler-users-group' },
    to: 'users',
    action: 'list',
    subject: 'users',
  },
  {
    title: 'Controle de Acesso',
    icon: { icon: 'tabler-smart-home' },
    children: [
      { title: 'Perfis', to: 'acesso-perfis' },
      { title: 'Permissões', to: 'acesso-permissoes' },
    ],
  },
  {
    title: 'Fornecedor',
    icon: { icon: 'tabler-template' },
    to: 'fornecedor',
    action: 'list',
    subject: 'fornecedor',
  },
  {
    title: 'Empresa',
    icon: { icon: 'tabler-template' },
    to: 'empresa',
    action: 'list',
    subject: 'empresa',
  },
  {
    title: 'Diretor',
    icon: { icon: 'tabler-template' },
    to: 'diretor',
    action: 'list',
    subject: 'diretor',
  },
{
                    title: 'Produto',
                    icon: { icon: 'tabler-template' },
                    to: 'produto',
                    action: 'list',
                    subject: 'produto',
                },
]