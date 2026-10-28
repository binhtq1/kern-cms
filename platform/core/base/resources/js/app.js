import { axios, HttpClient } from './utilities'
import lodash from 'lodash'

window._ = lodash

window.axios = axios

window.$httpClient = new HttpClient()

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
    },
})

