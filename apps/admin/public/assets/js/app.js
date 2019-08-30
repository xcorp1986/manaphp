Vue.prototype.$axios = axios;
Vue.prototype.$moment = moment;
Vue.prototype.$qs = Qs;
Vue.prototype._ = _;
Vue.prototype.sessionStorage = window.sessionStorage;
Vue.prototype.localStorage = window.localStorage;
Vue.prototype.console = console;

(function () {
    let urlKey = `last_url_query.${document.location.pathname}`;
    let last_url_query = localStorage.getItem(urlKey);
    window.history.replaceState(null, null, last_url_query === null ? '?' : last_url_query);

    window.onbeforeunload = (e) => {
        localStorage.setItem(urlKey, document.location.search);
    };
}());


document.location.query = document.location.search !== '' ? Qs.parse(document.location.search.substr(1)) : {};

//axios.defaults.baseURL = 'http://www.manaphp.com';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

axios.interceptors.request.use(function (config) {
    if (typeof vm.loading == 'boolean') {
        vm.loading = true;
    }

    config.url += config.url.indexOf('?') === -1 ? '?ajax' : '&ajax';
    return config;
});

axios.interceptors.response.use(function (res) {
        if (typeof vm.loading === 'boolean') {
            vm.loading = false;
        }

        if (!Vue.config.silent) {
            var now = new Date();
            console.warn('[AJAX][%s.%i][%s][%s] %o', now.toLocaleString('en-GB'), now.getMilliseconds(), res.headers['x-response-time'], res.config.url, res);
        }

        if (typeof res.data === 'string') {
            vm.$alert(res.data, '服务器错误', {customClass: 'error-response'});
        }

        if (res.data.code === 0) {
            if (res.data.message) {
                vm.$message({type: 'success', duration: 1000, message: res.data.message});
            } else if (res.config.method !== 'get') {
                vm.$message({type: 'success', duration: 1000, message: '操作成功'});
            }
        }
        return res;
    },
    function (error) {
        if (typeof vm.loading == 'boolean') {
            vm.loading = false;
        }

        console.log(error);
        if (error.response.status) {
            switch (error.response.status) {
                case 400:
                    alert(error.response.data.message);
                    break;
                case 401:
                    window.location.href = window.login_url;
                    break;
                default:
                    alert(error.response.message || '网络错误，请稍后重试: ' + error.response.status);
                    break;
            }
        } else {
            alert('网络错误，请稍后重试。');
        }
    });
Vue.mixin({
    filters: {
        date: function (value, format = 'YYYY-MM-DD HH:mm:ss') {
            return value ? moment(value * 1000).format(format) : '';
        },
        json: function (value) {
            return JSON.stringify(typeof value === 'string' ? JSON.parse(value) : value, null, 2);
        }
    },

    methods: {
        ajax_get: function (url, data, success) {
            if (!success && typeof data === 'object') {
                success = function (res) {
                    if (_.isArray(data)) {
                        data.length = 0;
                        if (_.isArray(res)) {
                            res.forEach(function (v) {
                                data.push(v);
                            })
                        }
                    } else {
                        Object.keys(res).forEach(function (key) {
                            data[key] = res[key];
                        })
                    }
                }
            } else if (typeof data === 'function') {
                success = data;
                data = null;
            } else if (data) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + Qs.stringify(data);
            }

            if (success && /\bcache=[12]\b/.test(url) && localStorage.getItem('axios.cache.enabled') !== '0') {
                var cache_key = 'axios.cache.' + url;
                let cache_value = sessionStorage.getItem(cache_key);
                if (cache_value) {
                    success.bind(this)(JSON.parse(cache_value));
                    return;
                }
            }

            return this.$axios.get(url).then(function (res) {
                if (res.data.code === 0) {
                    if (success) {
                        if (cache_key) {
                            sessionStorage.setItem(cache_key, JSON.stringify(res.data.data));
                        }
                        success.bind(this)(res.data.data);
                    }
                } else if (typeof res.data.message !== 'undefined') {
                    this.$alert(res.data.message);
                }
                return res;
            }.bind(this));
        },
        ajax_post: function (url, data, success) {
            if (typeof data === 'function') {
                success = data;
                data = {};
            }

            var config = {};
            if (data instanceof FormData) {
                config.headers = {'Content-Type': 'multipart/form-data'};
            }

            return this.$axios.post(url, data, config).then(function (res) {
                if (res.data.code === 0 && success) {
                    success.bind(this)(res.data.data);
                }

                if (res.data.message !== '') {
                    this.$alert(res.data.message);
                }
                return res
            }.bind(this));
        },
        reload: function () {
            if (typeof this.request === 'undefined' || typeof this.response === 'undefined') {
                alert('bad reload');
            }

            var qs = this.$qs.stringify(this.request);
            window.history.replaceState(null, null, qs ? ('?' + qs) : '');
            document.location.query = document.location.search !== '' ? Qs.parse(document.location.search.substr(1)) : {};
            this.response = [];
            this.$axios.get(document.location.href).then(function (res) {
                if (res.data.code !== 0) {
                    this.$alert(res.data.message);
                } else {
                    this.response = res.data.data;
                }
            }.bind(this));
        },
        format_date: function (value) {
            return value ? this.$moment(value * 1000).format('YYYY-MM-DD HH:mm:ss') : '';
        },
        fDate: function (row, column, value) {
            return this.format_date(value);
        },

        fEnabled: function (row, column, value) {
            return ['禁用', '启用'][value];
        },
        do_create: function (create) {
            var success = true;
            if (typeof create === 'string') {
                this.$refs[create].validate(valid => success = valid);
            }
            success && this.ajax_post(window.location.pathname + "/create", this.create, function (res) {
                this.createVisible = false;
                this.$refs.create.resetFields();
                this.reload();
            });
        },
        show_edit: function (row) {
            this.edit = Object.assign({}, row);
            this.editVisible = true;
        },
        do_edit: function () {
            this.ajax_post(window.location.pathname + "/edit", this.edit, function (res) {
                this.editVisible = false;
                this.reload();
            });
        },
        show_detail: function (row, action) {
            this.detailVisible = true;

            var data = {};
            var key = Object.keys(row)[0];
            data[key] = row[key];

            this.ajax_get(window.location.pathname + '/' + (action ? action : "/detail"), data, function (res) {
                this.detail = res;
            });
        },
        do_delete: function (row, name = '') {
            var data = {};
            var keys = Object.keys(row);
            var key = keys[0];
            data[key] = row[key];

            if (!name) {
                name = (typeof keys[1] !== 'undefined' && keys[1].indexOf('_name')) ? row[keys[1]] : row[key];
            }

            if (window.event.ctrlKey) {
                this.ajax_post(window.location.pathname + "/delete", data, function (res) {
                    this.reload();
                });
            } else {
                this.$confirm('确认删除 `' + (name ? name : row[key]) + '` ?').then(function (value) {
                    this.ajax_post(window.location.pathname + "/delete", data, function (res) {
                        this.reload();
                    });
                }.bind(this));
            }
        },
    },
    created: function () {
        if (this.$parent !== undefined) {
            return;
        }

        if (typeof this.request !== 'undefined' && typeof this.response !== 'undefined') {
            let qs = this.$qs.parse(document.location.query);
            for (let k in qs) {
                let v = qs[k];

                if (/^\d+$/.test(v) && typeof this.request[k] !== 'string') {
                    this.request[k] = parseInt(v);
                } else {
                    this.request[k] = v;
                }
            }

            this.$watch('request', _.debounce(function () {
                this.reload();
            }, 500), {deep: true});

            this.reload();
        }
    }
});

Vue.component('pager', {
    props: ['request', 'response'],
    template: ' <el-pagination background :current-page="response.page"\n' +
        '                   :page-size="request.size"\n' +
        '                   :page-sizes="[10,20,25,50,100,500,1000]"\n' +
        '                   @current-change="request.page=$event"\n' +
        '                   @size-change="request.size=$event; request.page=1"\n' +
        '                   :total="response.count" layout="sizes,total, prev, pager, next, jumper"></el-pagination>\n',
    watch: {
        request: {
            handler: function () {
                for (var field in this.request) {
                    if (field === 'page' || field === 'size') {
                        continue;
                    }
                    if (field in this.last_request && this.last_request[field] != this.request[field]) {
                        this.response.page = 1;
                        this.request.page = 1;
                        this.last_request = Object.assign({}, this.request);
                        break;
                    }
                }
            },
            deep: true
        }
    },
    data: function () {
        return {
            last_request: Object.assign({}, this.request)
        }
    }
});

Vue.component('date-picker', {
    props: ['value'],
    template: '<el-date-picker v-model="time" type="daterange" start-placeholder="开始日期"\n ' +
        '                            end-placeholder="结束日期" value-format="yyyy-MM-dd" size="small"\n ' +
        ' :picker-options="pickerOptions" @change="change"' +
        '></el-date-picker>',
    methods: {
        change: function (value) {
            this.$emit('input', value);
        }
    },
    data: function () {
        return {
            time: this.value,
            pickerOptions: {
                shortcuts: [
                    {
                        text: '今天',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setTime(start.getTime());
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '昨天',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24);
                            end.setDate(end.getDate() - 1);
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '最近三天',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24 * 3);
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '最近一周',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '最近一个月',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24 * 30);
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '最近三个月',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24 * 90);
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '最近一年',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setTime(start.getTime() - 3600 * 1000 * 24 * 365);
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '本周',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setDate(start.getDate() - start.getDay() + 1);
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '本月',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setDate(1);
                            picker.$emit('pick', [start, end]);
                        }
                    },
                    {
                        text: '本季',
                        onClick: function (picker) {
                            var end = new Date();
                            var start = new Date();
                            start.setMonth(parseInt(start.getMonth() / 3) * 3);
                            start.setDate(1);
                            picker.$emit('pick', [start, end]);
                        }
                    }
                ]
            }
        }
    }
});

Vue.component('my-menu', {
    template: `
<el-menu :default-active="active" class="el-menu-vertical-demo" :unique-opened="true">
    <template v-for="group in groups">
        <el-submenu :index="group.group_name">
            <template slot="title">
                <i :class="group.icon"></i>
                <span>{{group.group_name}}</span>
            </template>
            <template v-for="item in group.items ">
                <el-menu-item :index="item.url"><i :class="item.icon"></i>
                    <el-link :href="item.url">{{item.item_name}}</el-link>
                </el-menu-item>
            </template>
        </el-submenu>
     </template>
</el-menu>`,
    created() {
        this.ajax_get('/menu/my?cache=2', function (res) {
            this.groups = res;
        })
    },
    data() {
        return {
            active: location.pathname,
            groups: []
        }
    }
});

Vue.component('axios-cache-switcher', {
    template: `
    <div>
        <div v-if="enabled" @click="localStorage.setItem('axios.cache.enabled','0');enabled=false">缓存 (√)</div>
        <div v-else @click="localStorage.setItem('axios.cache.enabled','1');enabled=true">缓存 (×)</div>
    </div>`,
    data() {
        return {
            enabled: localStorage.getItem('axios.cache.enabled') !== '0'
        }
    }
});
