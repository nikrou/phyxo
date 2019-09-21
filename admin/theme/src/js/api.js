const POST_PARAMS = {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded' // @TODO: send application/json but need to retrieve stream in WS
  },
  credentials: 'same-origin'
};

const encodeParamsToBody = (params = []) => {
  const post_params = [];
  for (let param in params) {
    post_params.push(param + '=' + encodeURIComponent(params[param]));
  }

  return { body: post_params.join('&') };
};

export const updateExtension = (extensionType, extensionId, revisionId) => {
  const fetch_url = `${ws_url}?method=pwg.extensions.update`;
  const params = {
    type: extensionType,
    id: extensionId,
    revision: revisionId,
    pwg_token: pwg_token
  };

  return fetch(fetch_url, {
    ...POST_PARAMS,
    ...encodeParamsToBody(params)
  })
    .then(response => response)
    .catch(err => console.error(err));
};

export const updateIgnore = ({
  extensionType,
  extensionId = null,
  reset = false
}) => {
  const fetch_url = `${ws_url}?method=pwg.extensions.ignoreUpdate`;
  const params = {
    type: extensionType,
    pwg_token: pwg_token
  };

  if (reset) {
    params.reset = reset;
  }

  if (extensionId) {
    params.id = extensionId;
  }

  return fetch(fetch_url, {
    ...POST_PARAMS,
    ...encodeParamsToBody(params)
  })
    .then(response => response)
    .catch(err => console.error(err));
};

export const performAction = (plugin, action) => {
  const fetch_url = `${ws_url}?method=pwg.plugins.performAction`;
  const params = {
    plugin,
    action,
    pwg_token: pwg_token
  };

  return fetch(fetch_url, {
    ...POST_PARAMS,
    ...encodeParamsToBody(params)
  })
    .then(response => response)
    .catch(err => console.error(err));
};
