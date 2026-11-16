import { parseDotenv } from '@/utils/dotenv'
import { expect, test } from 'vite-plus/test'

test('reads the keys of a .env file', () => {
  expect(
    parseDotenv(
      [
        '# Database',
        'DB_DATABASE=stry',
        'export DB_USERNAME = user',
        'DB_PASSWORD="p@ss #word"',
        "APP_NAME='My app' # comment",
        'APP_KEY=base64:abc= # comment',
        'MAIL_FROM_NAME="${APP_NAME}"',
        '',
        'not a line',
      ].join('\n'),
    ),
  ).toEqual({
    DB_DATABASE: 'stry',
    DB_USERNAME: 'user',
    DB_PASSWORD: 'p@ss #word',
    APP_NAME: 'My app',
    APP_KEY: 'base64:abc=',
    MAIL_FROM_NAME: '${APP_NAME}',
  })
})

test('unescapes double-quoted values', () => {
  expect(parseDotenv('KEY="say \\"hi\\"\\nbye"\r\nOTHER=1')).toEqual({ KEY: 'say "hi"\nbye', OTHER: '1' })
})
