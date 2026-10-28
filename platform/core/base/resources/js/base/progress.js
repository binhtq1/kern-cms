import NProgress from "nprogress/nprogress";

/**
 * @param {string} color
 * @returns {void}
 */
function injectCSS(color) {
    const element = document.createElement("style");
    element.textContent = `
        #nprogress {
          pointer-events: none;
        }

        #nprogress .bar {
          background: ${color};

          position: fixed;
          z-index: 1031;
          top: 0;
          left: 0;

          width: 100%;
          height: 2px;
        }

        #nprogress .peg {
          display: block;
          position: absolute;
          right: 0px;
          width: 100px;
          height: 100%;
          box-shadow: 0 0 10px ${color}, 0 0 5px ${color};
          opacity: 1.0;

          -webkit-transform: rotate(3deg) translate(0px, -4px);
              -ms-transform: rotate(3deg) translate(0px, -4px);
                  transform: rotate(3deg) translate(0px, -4px);
        }

        #nprogress .spinner {
          display: block;
          position: fixed;
          z-index: 1031;
          top: 15px;
          right: 15px;
        }

        #nprogress .spinner-icon {
          width: 18px;
          height: 18px;
          box-sizing: border-box;

          border: solid 2px transparent;
          border-top-color: ${color};
          border-left-color: ${color};
          border-radius: 50%;

          -webkit-animation: nprogress-spinner 400ms linear infinite;
                  animation: nprogress-spinner 400ms linear infinite;
        }

        .nprogress-custom-parent {
          overflow: hidden;
          position: relative;
        }

        .nprogress-custom-parent #nprogress .spinner,
        .nprogress-custom-parent #nprogress .bar {
          position: absolute;
        }

        @-webkit-keyframes nprogress-spinner {
          0%   { -webkit-transform: rotate(0deg); }
          100% { -webkit-transform: rotate(360deg); }
        }
        @keyframes nprogress-spinner {
          0%   { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(element);
}

/**
 * @param {number} delay
 * @param {string} color
 * @param {boolean} includeCSS
 * @param {boolean} showSpinner
 * @returns {void}
 */
export default function setupProgress({
    delay = 250,
    color = "var(--primary)",
    includeCSS = true,
    showSpinner = false,
} = {}) {
    let active = 0;
    let timer = null;
    const start = () => {
        active++;
        if (active === 1) timer = setTimeout(() => NProgress.start(), delay);
    };
    const finish = () => {
        active = Math.max(0, active - 1);
        if (active > 0) return;
        clearTimeout(timer);
        timer = null;
        NProgress.done();
    };

    window.$httpClient?.beforeSend(start);
    window.$httpClient?.completed(finish);
    if (window.axios) {
        window.axios.interceptors.request.use((config) => {
            start();
            return config;
        });
        window.axios.interceptors.response.use(
            (response) => {
                finish();
                return response;
            },
            (error) => {
                finish();
                return Promise.reject(error);
            },
        );
    }

    NProgress.configure({ showSpinner });
    if (includeCSS) injectCSS(color);
}
