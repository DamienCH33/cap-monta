import { APP_PATHS } from './app.paths';
import { routes } from './app.routes';

describe('APP_PATHS', () => {
  it('lists exactly the routes of the application', () => {
    const paths = routes
      .map((route) => route.path)
      .filter((path): path is string => 'string' === typeof path && '**' !== path);

    expect([...APP_PATHS].sort()).toEqual([...paths].sort());
  });
});
