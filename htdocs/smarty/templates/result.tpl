{include file='header.tpl'}
		<center>
			<p>Question: <div id="question">{$query}</div></p>
			<p>Response: <div id="response">{$response}</div></p>
		</center>
<script type="text/javascript">window.onload=startPolling({$id})</script>
{include file='footer.tpl'}