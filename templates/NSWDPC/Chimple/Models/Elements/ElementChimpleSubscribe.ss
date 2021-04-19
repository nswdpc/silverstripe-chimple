<% if $SubscribeForm %>
    <% include ChimpleSubscribeTitle %>
    <div class="element element-content<% if $StyleVariant %> $StyleVariant<% end_if %>">

        <% if $BeforeFormContent %>
            <div class="before">
                {$BeforeFormContent}
            </div>
        <% end_if %>

        <% if $Image %>
            <div class="image">
                {$Image}
            </div>
        <% end_if %>

        <div class="form">
            {$SubscribeForm}
        </div>

        <% if $BeforeFormContent %>
            <div class="after">
                {$BeforeFormContent}
            </div>
        <% end_if %>

    </div>
<% end_if %>
