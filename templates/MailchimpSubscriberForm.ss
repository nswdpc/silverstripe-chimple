<form {$FormAttributes}>
    <% if $Message %>
        <div id="{$FormName}_error" class="message $MessageType">{$Message}</div>
    <% end_if %>
    <fieldset>
    <% if $Legend %>
        <legend>$Legend</legend>
    <% end_if %>
        <div class="form-group">
            <% loop $VisibleFields %>
                {$FieldHolder}
            <% end_loop %>
            <% loop $HiddenFields %>
                {$FieldHolder}
            <% end_loop %>
        </div>
    <% loop Actions %>
        <div class="form-group actions">
            {$Field}
        </div>
    <% end_loop %>
    </fieldset>
</form>
