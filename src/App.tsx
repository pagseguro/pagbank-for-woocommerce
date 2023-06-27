import { Box, Button, ChakraProvider, Image, Input, InputGroup, InputLeftAddon, InputRightElement, Text } from '@chakra-ui/react';
import logoPagBank from './assets/logo-pagbank.svg'
import { useMemo, useState } from 'react';

const urlRegex = /https?:\/\/(www\.)?[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_+.~#?&//=]*)/
const httpsRegex = /^https?:\/\//

const validateUrl = (url: string) => urlRegex.test(url)

const App: React.FC = () => {
  const [url, setUrl] = useState<string>('')
  const isUrl = useMemo(() => validateUrl(`https://${url}`), [url])

  const onSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    const trimmedUrl = url.trim().replace(/\/$/, "");
    const redirectUrl = `https://${trimmedUrl}/wp-admin/plugin-install.php?s=pagbank-woocommerce&tab=search&type=term`

    if(!validateUrl(redirectUrl)) {
      alert('URL inválida')

      return
    }

    window.location.href = `${redirectUrl}`
  }

  const onChangeUrl = (event: React.ChangeEvent<HTMLInputElement>) => {
    const { value } = event.target
    const clearedValue = value.trim()
    
    if(httpsRegex.test(clearedValue)) {
      event.target.value = clearedValue.replace(httpsRegex, '')
    }

    event.target.value = event.target.value.replace(/\s/g, '')

    setUrl(event.target.value)
  }
  
  return (
    <ChakraProvider>
      <Box h="100vh" marginX="auto" display="flex" flexDirection="column" alignItems="center" justifyContent="center" background="linear-gradient(81.51deg,#ffe72d 2.89%,#fff4a0 87.51%,#fff 104.08%)">
        <Image src={logoPagBank} alt="PagBank" w={96} marginX="auto"/>

        <Text mt={4}>Preencha o campo abaixo com a URL da sua loja e iremos te redirecionar para instalar o nosso plugin:</Text>

        <Box as="form" w="100%" maxW="500px" display="flex" alignItems="center" flexDir="column" onSubmit={onSubmit}>
          <InputGroup mt={12} size="lg" borderColor="#1bb99a">
            <InputLeftAddon children="https://" backgroundColor="#1bb99a" color="#ffffff" />
            <Input placeholder='seu-site.com.br' pr={28} onChange={onChangeUrl} color="#1bb99a" backgroundColor="#ffffff" />
            <InputRightElement w={24} >
              <Button type="submit" w="100%" mr={1} isDisabled={!isUrl} backgroundColor="#1bb99a" color="#ffffff" _hover={{backgroundColor: '#1a947b'}}>
                Instalar
              </Button>
            </InputRightElement>
          </InputGroup>
        </Box>
      </Box>
    </ChakraProvider>
  );
}

export default App;