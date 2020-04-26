<script src="js/vue_2_6_11_dev.js" type="text/javascript"></script>
<div class="col-sm-12 col-xs-11">
    <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
    <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
            <div style="position: fixed; left: 50%; top: 50%;">
                <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                Loading...
            </div>
    </div>
    <h3>System Settings</h3>
    Some of these settings can be overriden in the configuration file.<br>
    Operate with caution.
    <form class="form-horizontal" id="settingsForm" action="" method="post">
        <input type="hidden" name="site" value="<?=SITE?>">
        <input type="hidden" name="ajaxCont" value="data">
        <input type="hidden" name="type" value="settings-set">
        <div class="form-group">
            <label for="leave-detection" class="control-label col-xs-2">Auto Leave Detection</label>
            <div class="col-xs-10">
                <div class="checkbox">
                    <label><input type="checkbox" name="leave-detection" id="leave-detection" checked="checked"> Auto Leave Detection</label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="leave-cause" class="control-label col-xs-2">Auto-Leave Cause</label>
            <div class="col-xs-10">
                <input type="text" name="leave-cause" required="" autocomplete="on" class="form-control" id="leave-cause" placeholder="Cause to use for auto leaves">
            </div>
        </div>
        <div class="form-group">
            <label for="ts3-check" class="control-label col-xs-2">TS3 Identity Check</label>
            <div class="col-xs-10">
                <div class="checkbox">
                    <label><input type="checkbox" name="ts3-check" id="ts3-check" checked="checked"> TS3 Member unknown IDs check</label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="ts3-member-groups" class="control-label col-xs-2">TS3 Member Groups</label>
            <div class="col-xs-10">
                <input type="text" name="ts3-member-groups" required="" autocomplete="on" class="form-control" id="ts3-member-groups" placeholder="TS3 Groups that a member has">
            </div>
        </div>
        <div class="form-group">
            <label for="GUEST_NOTIFY_ENABLE" class="control-label col-xs-2">TS3 Guest Poke</label>
            <div class="col-xs-10">
                <div class="checkbox">
                    <label><input type="checkbox" name="GUEST_NOTIFY_ENABLE" id="GUEST_NOTIFY_ENABLE" checked="checked"> Ts3 Guest Poke Enabled</label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="GUEST_WATCHER_GROUP" class="control-label col-xs-2">Poke group</label>
            <div class="col-xs-10">
                <input type="text" name="GUEST_WATCHER_GROUP" required="" autocomplete="on" class="form-control" id="GUEST_WATCHER_GROUP" placeholder="TS3 Group ID should be poked/notified.">
            </div>
        </div>
        <div class="form-group">
            <label for="GUEST_GROUP" class="control-label col-xs-2">Guest Group</label>
            <div class="col-xs-10">
                <input type="text" name="GUEST_GROUP" required="" autocomplete="on" class="form-control" id="GUEST_GROUP" placeholder="TS3 Group ID for which should be poked.">
            </div>
        </div>
        <div class="form-group">
            <label for="GUEST_POKE_MSG" class="control-label col-xs-2">Guest-Poke notify message</label>
            <div class="col-xs-10">
                <input type="text" name="GUEST_POKE_MSG" required="" autocomplete="on" class="form-control" id="GUEST_POKE_MSG" placeholder="TS3 guest notify message.">
            </div>
        </div>
        <div class="form-group">
            <label for="GUEST_CHANNEL" class="control-label col-xs-2">Guest Channel</label>
            <div class="col-xs-10">
                <input type="text" name="GUEST_CHANNEL" required="" autocomplete="on" class="form-control" id="GUEST_CHANNEL" placeholder="Guest notify channel ID">
            </div>
        </div>
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <button type="submit" class="btn btn-warning" id="submitSettings"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </form>
    <?php if(hasPermission(PERM_CLANTOOL_TEST)) { ?>
    <h3>Channel Groups</h3>
    <div id="vue-channel-groups">
        <div class="panel panel-default" v-for="group in groups" :key="group.id">
            <div class="panel-heading">
                {{group.gname}} <button @click="deleteGroup(group.id)">delete</button>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <span v-for="channel in group.channels" :key="channel.cid">
                        {{channel.cname}} ({{channel.cid}})
                    </span>
                </div>
                <div class="form-group">
                    <div class="col-xs-10">
                        <tsselect v-model="group.addChannel">
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-xs-10">
                        <button @click="addChannel(group.id)">Add Channel</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-horizontal">
            <div class="form-group">
                <label for="group_name" class="control-label col-xs-2">Group Name</label>
                <div class="col-xs-10">
                    <input v-model="group_name" type="text" required="" autocomplete="on" class="form-control" id="group_name" placeholder="Channel Group Name">
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <button v-on:click="createGroup" class="btn btn-warning"><i class="fas fa-save"></i> Create Group</button>
                </div>
            </div>
        </div>
    </div>
    <script type="text/x-template" id="tsselect-template">
        <select ref="select" class="form-control">
        </select>
    </script>
    <?php } ?>
</div>
<script type="text/javascript">
Vue.component("tsselect", {
    props: ["value"],
    template: "#tsselect-template",
    mounted: function() {
        var vm = this;
        $(this.$el)
        // init select2
        .select2({
            ajax: {
                url: SITE_URL,
                type: 'get',
                dataType: 'json',
                delay: 500, // ms
                data: function(params) {
                    var query = {
                        'site' : VAR_SITE,
                        'ajaxCont' : 'data',
                        'type' : 'ts-channel-search-select2',
                        'key' : params.term
                    }
                    
                    return query;
                }
            },
            placeholder: "TS3 Channel Suche",
            allowClear: true
        })
        //.val(this.value)
        //.trigger("change")
        // emit event on change.
        .on("change", function() {
            // misusing value/input event chain to return object
            vm.$emit("input", {'cid': this.value, 'cname': this.textContent});
        });
    },
    watch: {
        // watching currently broken
        value: function(value) {
        // update value
        //$(this.$el)
        //  .val(value)
        //  .trigger("change");
        }
    },
    destroyed: function() {
        $(this.$el)
        .off()
        .select2("destroy");
    }
});
$( document ).ready(function() {
    const loadingDiv = $('#loading');
    const errorDiv = $('#error');
    function loadSettings() {
        loadingDiv.show();
        $.ajax({
            url: 'index.php',
            type: 'get',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'settings-load',
            }
        }).done(function(data){
            if(data != null){
                $('#leave-detection').prop('checked',data['leave-detection']);
                $('#leave-cause').val(data['leave-cause']);
                $('#ts3-check').prop('checked',data['ts3-check']);
                $('#ts3-member-groups').val(data['ts3-member-groups']);
                
                $('#GUEST_NOTIFY_ENABLE').prop('checked',data['GUEST_NOTIFY_ENABLE']);
                $('#GUEST_WATCHER_GROUP').val(data['GUEST_WATCHER_GROUP']);
                $('#GUEST_GROUP').val(data['GUEST_GROUP']);
                $('#GUEST_POKE_MSG').val(data['GUEST_POKE_MSG']);
                $('#GUEST_CHANNEL').val(data['GUEST_CHANNEL']);
            }
            loadingDiv.hide();
        }).fail(function(data){
            console.error(data);
            loadingDiv.hide();
        });
    }
    $("#settingsForm").submit(function(e) {
        loadingDiv.show();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: $(this).serialize()
        }).done(function(data){
            if(data){
                errorDiv.hide();
            }else{
                errorDiv.show();
                errorDiv.txt('Unable to save!');
            }
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    loadSettings();
    <?php if(hasPermission(PERM_CLANTOOL_TEST)) { ?>
    
    var CGApp = new Vue({
        el: '#vue-channel-groups',
        data: {
            groups: null,
            group_name: ""
        },
        methods: {
            loadGroups: function() {
                getJson('ts-channel-groups')
                .then(res => {
                    console.log('res',res);
                    this.groups = res;
                }).catch(error => {
                    console.error(error);
                }).then(() => loadingDiv.hide());
            },
            createGroup: function() {
                console.log("creating group",this.group_name);
                let t_name = this.group_name;
                loadingDiv.show();
                postJson('ts-channel-group-create',{'name': t_name})
                .then(res => {
                    Vue.set(this.groups,res.id,res);
                    loadingDiv.hide();
                });
            },
            deleteGroup: function(gid) {
                loadingDiv.show();
                postJson('ts-channel-group-delete', {'gid':gid})
                .then(res => Vue.delete(this.groups, gid))
                .finally(() => loadingDiv.hide());
            },
            addChannel: function(gid) {
                let elem = this.groups[gid].addChannel;
                loadingDiv.show();
                postJson('ts-channel-group-add-channel', {'gid':gid,'cid': elem.cid})
                .then(res => this.groups[gid].channels.push(elem))
                .finally(() => loadingDiv.hide());
            },
            removeChannel: function(gid,cid) {
                
            },
            test: function(text) {
                console.log('Info',text);
                group_name = "asdasd";
            }
        },
        beforeMount(){
            this.loadGroups()
        }
    });
    //CGApp.test("asd");
    <?php } ?>
});
</script>
